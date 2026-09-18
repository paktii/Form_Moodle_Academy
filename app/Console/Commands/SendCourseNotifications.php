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
        $processed = 0;
        $failed = 0;

        while ($processed < $limit) {
            $notification = $this->claimNext($maxAttempts);

            if ($notification === null) {
                break;
            }

            try {
                $request = CourseRequest::query()->findOrFail($notification->request_id);
                [$subject, $body] = $this->messageFor($notification->notification_type, $request);

                Mail::raw($body, function ($message) use ($notification, $subject): void {
                    $message->to($notification->recipient_email)->subject($subject);
                });

                $notification->update([
                    'delivery_status' => 'SENT',
                    'sent_at' => now(),
                    'last_error' => null,
                ]);
            } catch (Throwable $exception) {
                $failed++;
                $notification->update([
                    'delivery_status' => 'FAILED',
                    'last_error' => Str::limit($exception->getMessage(), 4000, ''),
                ]);
                report($exception);
            }

            $processed++;
        }

        $this->info("Processed $processed notification(s); $failed failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function claimNext(int $maxAttempts): ?NotificationOutbox
    {
        return DB::connection('course133')->transaction(function () use ($maxAttempts) {
            $notification = NotificationOutbox::query()
                ->whereIn('delivery_status', ['PENDING', 'FAILED', 'SENDING'])
                ->where('attempt_count', '<', $maxAttempts)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if ($notification === null) {
                return null;
            }

            $notification->update([
                'delivery_status' => 'SENDING',
                'attempt_count' => $notification->attempt_count + 1,
                'last_error' => null,
            ]);

            return $notification;
        });
    }

    private function messageFor(string $type, CourseRequest $request): array
    {
        $requestUrl = match ($type) {
            'OFFICER_REVIEW_REQUIRED', 'COURSE_ID_REQUIRED' => route('officer.show', $request->request_id),
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
            default => 'สถานะคำร้องมีการเปลี่ยนแปลง',
        };

        return [
            "[$request->request_no] $action",
            "$action\n\nเลขที่คำร้อง: $request->request_no\nชื่อโครงการ: $request->project_name\nรายละเอียด: $requestUrl",
        ];
    }
}
