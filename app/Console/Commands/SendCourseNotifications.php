<?php

namespace App\Console\Commands;

use App\Models\CourseRequest;
use App\Models\NotificationOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendCourseNotifications extends Command
{
    protected $signature = 'course-notifications:send {--limit=50 : Maximum messages to process} {--attempts=5 : Maximum delivery attempts}';

    protected $description = 'Send pending SWU Moodle Academy workflow email notifications';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = max(1, (int) $this->option('attempts'));
        $staleAfterMinutes = max(1, (int) config('course-workflow.notifications.stale_after_minutes', 5));
        $processed = 0;
        $failed = 0;

        while ($processed < $limit) {
            $notification = $this->claimNext($maxAttempts, $staleAfterMinutes);

            if ($notification === null) {
                break;
            }

            try {
                $request = CourseRequest::query()->findOrFail($notification->request_id);
                [$subject, $body] = $this->messageFor($notification->notification_type, $request);

                Mail::raw($body, function ($message) use ($notification, $subject): void {
                    $message->to($notification->recipient_email)->subject($subject);

                    if (filter_var($notification->reply_to_email, FILTER_VALIDATE_EMAIL)) {
                        $message->replyTo($notification->reply_to_email, $notification->reply_to_name ?: null);
                    }
                });

                $notification->update([
                    'delivery_status' => 'SENT',
                    'sent_at' => now(),
                    'processing_started_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => null,
                ]);
            } catch (Throwable $exception) {
                $failed++;
                $notification->update([
                    'delivery_status' => 'FAILED',
                    'processing_started_at' => null,
                    'next_attempt_at' => now()->addSeconds($this->retryDelaySeconds($notification->attempt_count)),
                    'last_error' => Str::limit($exception->getMessage(), 4000, ''),
                ]);
                report($exception);
            }

            $processed++;
        }

        $this->info("Processed $processed notification(s); $failed failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function claimNext(int $maxAttempts, int $staleAfterMinutes): ?NotificationOutbox
    {
        return DB::connection('course133')->transaction(function () use ($maxAttempts, $staleAfterMinutes) {
            $now = now();
            $staleBefore = $now->copy()->subMinutes($staleAfterMinutes);

            $notification = NotificationOutbox::query()
                ->where('attempt_count', '<', $maxAttempts)
                ->where(function ($query) use ($now, $staleBefore): void {
                    $query->where('delivery_status', 'PENDING')
                        ->orWhere(function ($failed) use ($now): void {
                            $failed->where('delivery_status', 'FAILED')
                                ->where(function ($ready) use ($now): void {
                                    $ready->whereNull('next_attempt_at')
                                        ->orWhere('next_attempt_at', '<=', $now);
                                });
                        })
                        ->orWhere(function ($sending) use ($staleBefore): void {
                            $sending->where('delivery_status', 'SENDING')
                                ->where(function ($stale) use ($staleBefore): void {
                                    $stale->whereNull('processing_started_at')
                                        ->orWhere('processing_started_at', '<=', $staleBefore);
                                });
                        });
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if ($notification === null) {
                return null;
            }

            $notification->update([
                'delivery_status' => 'SENDING',
                'attempt_count' => $notification->attempt_count + 1,
                'processing_started_at' => $now,
                'next_attempt_at' => null,
                'last_error' => null,
            ]);

            return $notification;
        });
    }

    private function retryDelaySeconds(int $attemptCount): int
    {
        $baseDelay = max(1, (int) config('course-workflow.notifications.retry_delay_seconds', 30));
        $maxDelay = max($baseDelay, (int) config('course-workflow.notifications.max_retry_delay_seconds', 900));
        $multiplier = 2 ** max(0, min($attemptCount - 1, 10));

        return min($baseDelay * $multiplier, $maxDelay);
    }

    private function messageFor(string $type, CourseRequest $request): array
    {
        $requestUrl = match ($type) {
            'OFFICER_REVIEW_REQUIRED', 'COURSE_ID_REQUIRED', 'STUDENT_ROSTER_SUBMITTED', 'STUDENT_ROSTER_UPDATED', 'STUDENT_ROSTER_REOPEN_REQUESTED' => route('officer.show', $request->request_id),
            'APPROVAL_REQUIRED' => route('approver.show', $request->request_id),
            default => route('requests.show', $request->request_id),
        };

        $action = match ($type) {
            'OFFICER_REVIEW_REQUIRED' => 'มีคำร้องใหม่รอการตรวจสอบ',
            'APPROVAL_REQUIRED' => 'มีคำร้องรอการพิจารณาอนุมัติ',
            'REQUEST_RETURNED' => 'คำร้องถูกส่งกลับให้แก้ไข',
            'REQUEST_REJECTED' => 'คำร้องไม่ผ่านการอนุมัติ',
            'COURSE_ID_REQUIRED' => 'คำร้องได้รับอนุมัติและรอบันทึก Course ID',
            'COURSE_ID_RECORDED' => 'บันทึก Course ID เรียบร้อยแล้ว',
            'STUDENT_ROSTER_SUBMITTED' => 'ผู้ยื่นคำร้องแนบรายชื่อผู้เรียนแล้ว',
            'STUDENT_ROSTER_UPDATED' => 'ผู้ยื่นคำร้องอัปเดตรายชื่อผู้เรียน',
            'STUDENT_ROSTER_REOPEN_REQUESTED' => 'ผู้ยื่นคำร้องขอแก้ไขรายชื่อผู้เรียน',
            'STUDENT_ROSTER_ACKNOWLEDGED' => 'เจ้าหน้าที่รับทราบรายชื่อผู้เรียนแล้ว',
            'STUDENT_ROSTER_REOPENED' => 'เจ้าหน้าที่อนุญาตให้อัปโหลดรายชื่อใหม่',
            default => 'สถานะคำร้องมีการเปลี่ยนแปลง',
        };

        return [
            "[$request->request_no] $action",
            "$action\n\nเลขที่คำร้อง: $request->request_no\nชื่อโครงการ: $request->project_name\nรายละเอียด: $requestUrl",
        ];
    }
}
