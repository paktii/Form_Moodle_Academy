<?php

namespace App\Console\Commands;

use App\Models\CourseRequest;
use App\Models\NotificationOutbox;
use App\Services\DepartmentDirectory;
use App\Services\PersonnelDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
                $request = CourseRequest::query()->with('instructors', 'websiteDetail.platform', 'websiteContact', 'websiteContactChangeDetail', 'websiteStorageDetail', 'emeetingDetail', 'currentWebsiteContact', 'newWebsiteContact', 'currentEmeetingContact', 'emeetingContact', 'coordinatorContact')->findOrFail($notification->request_id);
                [$subject, $body, $html] = $this->messageFor($notification->notification_type, $request, $notification);
                $recipientEmail = $this->recipientEmailFor($notification);

                $configureMessage = function ($message) use ($recipientEmail, $subject, $notification): void {
                    $message->to($recipientEmail)->subject($subject);
                    $message->getSymfonyMessage()->getHeaders()->addTextHeader(
                        'X-Entity-Ref-ID',
                        'course-notification-'.$notification->getKey(),
                    );

                    $replyToAddress = (string) config('mail.from.address');
                    $replyToName = (string) config('mail.from.name');

                    if (filter_var($replyToAddress, FILTER_VALIDATE_EMAIL)) {
                        $message->replyTo($replyToAddress, $replyToName !== '' ? $replyToName : null);
                    }
                };

                if ($html !== null) {
                    Mail::html($html, $configureMessage);
                } else {
                    Mail::raw($body, $configureMessage);
                }

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
                                    $stale->whereNull('claimed_at')
                                        ->orWhere('claimed_at', '<=', $staleBefore);
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

    private function messageFor(string $type, CourseRequest $request, NotificationOutbox $notification): array
    {
        if (in_array($type, [
            'DEAN_REVIEW_REQUIRED',
            'OFFICER_REVIEW_REQUIRED',
            'REQUEST_COMPLETED_REQUESTER',
            'REQUEST_RESULT_UPDATED',
            'REQUEST_COMPLETED_FORM_HEAD',
        ], true)) {
            return $this->workflowTemplateMessage($type, $request, $notification);
        }

        if ($type === 'DEAN_REVIEW_REQUIRED') {
            $departmentDirectory = app(DepartmentDirectory::class);
            $departmentName = $departmentDirectory->name((int) $request->target_dept_id);
            $subdepartmentName = $request->target_subdept_id
                ? $departmentDirectory->subdepartmentName((int) $request->target_subdept_id)
                : '—';
            $activityPeriod = $this->thaiBuddhistDate($request->starts_on)
                .' ถึง '.$this->thaiBuddhistDate($request->ends_on);
            $instructorNames = $request->instructors
                ->pluck('instructor_name')
                ->filter(fn ($name) => filled($name))
                ->values();
            $expiresAt = now()->addMonthsNoOverflow((int) config('course-workflow.dean.review_link_months', 1));
            $expiresAtText = $this->thaiBuddhistDateTime($expiresAt);
            $detailsUrl = URL::temporarySignedRoute(
                'dean.review.confirm',
                $expiresAt,
                [
                    'id' => $request->request_id,
                    'notification' => $notification->getKey(),
                ],
            );
            if ($request->form_type_code === 'CC-ITED-01') {
                $website = $request->websiteDetail;
                $websiteContact = $request->websiteContact;
                $websitePeriod = $website?->site_scope_type === 'DEPARTMENT'
                    ? 'ไม่มีกำหนด'
                    : $this->thaiBuddhistDate($website?->service_starts_on).' ถึง '.$this->thaiBuddhistDate($website?->service_ends_on);
                $platformName = $website?->platform?->platform_name_th ?: '—';
                $subject = "[$request->request_no] มีคำขอใช้พื้นที่เว็บไซต์รอการรับรอง";
                $body = "มีคำขอใช้พื้นที่สำหรับจัดทำเว็บไซต์รอการรับรอง\n\n"
                    ."เลขที่คำร้อง: $request->request_no\n"
                    ."ส่วนงาน/คณะ/สำนัก: $departmentName\n"
                    ."หน่วยงานย่อย: $subdepartmentName\n"
                    ."ชื่อเว็บไซต์: ".($website?->website_name ?: '—')."\n"
                    ."ระยะเวลาใช้งาน: $websitePeriod\n"
                    ."รายละเอียดเว็บไซต์: $platformName\n"
                    ."ผู้ดูแลเว็บไซต์: ".($websiteContact?->full_name ?: '—')."\n\n"
                    ."ดูรายละเอียดและพิจารณาคำร้อง: $detailsUrl\n\n"
                    ."ลิงก์หมดอายุวันที่ $expiresAtText";
                $html = view('emails.website-dean-review', compact(
                    'request',
                    'website',
                    'websiteContact',
                    'detailsUrl',
                    'departmentName',
                    'subdepartmentName',
                    'websitePeriod',
                    'platformName',
                    'expiresAtText',
                ))->render();

                return [$subject, $body, $html];
            }
            if ($request->form_type_code === 'CC-ITED-02') {
                $website = $request->websiteContactChangeDetail;
                $currentWebsiteContact = $request->currentWebsiteContact;
                $newWebsiteContact = $request->newWebsiteContact;
                $subject = "[$request->request_no] มีคำขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์รอการรับรอง";
                $body = "มีคำขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์รอการรับรอง\n\n"
                    ."เลขที่คำร้อง: $request->request_no\n"
                    ."ส่วนงาน/คณะ/สำนัก: $departmentName\n"
                    ."หน่วยงานย่อย: $subdepartmentName\n"
                    ."ชื่อเว็บไซต์: ".($website?->website_name ?: '—')."\n"
                    ."โดเมน (URL): ".($website?->domain_url ?: '—')."\n"
                    ."ผู้ดูแลเว็บไซต์เดิม: ".($currentWebsiteContact?->full_name ?: '—')."\n"
                    ."ผู้ดูแลเว็บไซต์คนใหม่: ".($newWebsiteContact?->full_name ?: '—')."\n\n"
                    ."ดูรายละเอียดและพิจารณาคำร้อง: $detailsUrl\n\n"
                    ."ลิงก์หมดอายุวันที่ $expiresAtText";
                $html = view('emails.website-contact-change-dean-review', compact(
                    'request',
                    'website',
                    'currentWebsiteContact',
                    'newWebsiteContact',
                    'detailsUrl',
                    'departmentName',
                    'subdepartmentName',
                    'expiresAtText',
                ))->render();

                return [$subject, $body, $html];
            }
            if ($request->form_type_code === 'CC-ITED-04') {
                $website = $request->websiteStorageDetail;
                $websiteContact = $request->websiteContact;
                $subject = "[$request->request_no] มีคำขอเพิ่มพื้นที่บนเว็บไซต์รอการรับรอง";
                $body = "มีคำขอเพิ่มพื้นที่บนเว็บไซต์รอการรับรอง\n\n"
                    ."เลขที่คำร้อง: $request->request_no\n"
                    ."ส่วนงาน/คณะ/สำนัก: $departmentName\n"
                    ."หน่วยงานย่อย: $subdepartmentName\n"
                    ."ชื่อเว็บไซต์: ".($website?->website_name ?: '—')."\n"
                    ."โดเมน (URL): ".($website?->domain_url ?: '—')."\n"
                    ."พื้นที่ที่ขอเพิ่ม: ".($website?->requested_space_gb ?: 1)." GB\n"
                    ."ผู้ดูแลเว็บไซต์: ".($websiteContact?->full_name ?: '—')."\n\n"
                    ."ดูรายละเอียดและพิจารณาคำร้อง: $detailsUrl\n\n"
                    ."ลิงก์หมดอายุวันที่ $expiresAtText";
                $html = view('emails.website-storage-dean-review', compact(
                    'request',
                    'website',
                    'websiteContact',
                    'detailsUrl',
                    'departmentName',
                    'subdepartmentName',
                    'expiresAtText',
                ))->render();

                return [$subject, $body, $html];
            }
            if ($request->form_type_code === 'CC-ITED-05') {
                $detail = $request->emeetingDetail;
                $currentContact = $request->currentEmeetingContact;
                $contact = $request->emeetingContact;
                $actionLabel = $detail?->action_type === 'CHANGE_CONTACT'
                    ? 'เปลี่ยนแปลงสิทธิ์ผู้ดูแล'
                    : 'ขอสิทธิ์ผู้ดูแล';
                $subject = "[$request->request_no] มีคำขอสิทธิ์ผู้ดูแลกรอบการประชุมรอการรับรอง";
                $body = "มีคำขอสิทธิ์ผู้ดูแลกรอบการประชุม SWU e-Meeting รอการรับรอง\n\n"
                    ."เลขที่คำร้อง: $request->request_no\n"
                    ."ส่วนงาน/คณะ/สำนัก: $departmentName\n"
                    ."หน่วยงานย่อย: $subdepartmentName\n"
                    ."ขอรับบริการ: $actionLabel\n"
                    .($detail?->action_type === 'CHANGE_CONTACT' ? "ผู้ดูแลเดิม: ".($currentContact?->full_name ?: '—')."\n" : '')
                    ."ผู้ดูแลที่จะได้รับสิทธิ์: ".($contact?->full_name ?: '—')."\n\n"
                    ."ดูรายละเอียดและพิจารณาคำร้อง: $detailsUrl\n\n"
                    ."ลิงก์หมดอายุวันที่ $expiresAtText";

                $html = view('emails.emeeting-dean-review', compact(
                    'request',
                    'detail',
                    'currentContact',
                    'contact',
                    'actionLabel',
                    'detailsUrl',
                    'departmentName',
                    'subdepartmentName',
                    'expiresAtText',
                ))->render();

                return [$subject, $body, $html];
            }
            $subject = "[$request->request_no] มีคำร้องขอสร้างรายวิชารอการรับรอง";
            $body = "มีคำร้องขอสร้างรายวิชาในระบบ SWU Moodle Academy รอการรับรอง\n\n"
                ."เลขที่คำร้อง: $request->request_no\n"
                ."ส่วนงาน/คณะ/สำนัก: $departmentName\n"
                ."ภาควิชา/ส่วนงานย่อย: $subdepartmentName\n"
                ."ชื่อโครงการ: $request->project_name\n"
                ."ชื่อรายวิชา: $request->course_name_th\n"
                ."ระยะเวลาดำเนินกิจกรรม: $activityPeriod\n"
                ."รายชื่ออาจารย์ผู้สอน: ".($instructorNames->implode(', ') ?: '—')."\n"
                ."จำนวนผู้เรียนที่คาดการณ์: $request->expected_students คน\n\n"
                ."ดูรายละเอียดและพิจารณาคำร้อง: $detailsUrl\n\n"
                ."ลิงก์หมดอายุวันที่ $expiresAtText";
            $html = view('emails.dean-review', compact(
                'request',
                'detailsUrl',
                'departmentName',
                'subdepartmentName',
                'activityPeriod',
                'instructorNames',
                'expiresAtText',
            ))->render();

            return [$subject, $body, $html];
        }

        $requestUrl = match ($type) {
            'OFFICER_REVIEW_REQUIRED', 'COURSE_ID_REQUIRED', 'STUDENT_ROSTER_SUBMITTED', 'STUDENT_ROSTER_UPDATED', 'STUDENT_ROSTER_REOPEN_REQUESTED' => route('officer.show', $request->request_id),
            'APPROVAL_REQUIRED' => route('approver.show', $request->request_id),
            default => route('requests.show', $request->request_id),
        };

        $action = match ($type) {
            'OFFICER_REVIEW_REQUIRED' => 'มีคำร้องใหม่รอการตรวจสอบ',
            'APPROVAL_REQUIRED' => 'มีคำร้องรอการพิจารณาอนุมัติ',
            'REQUEST_REJECTED' => 'คำร้องไม่ผ่านการอนุมัติ',
            'DEAN_REJECTED' => 'คณบดีไม่รับรองคำร้อง',
            'COURSE_ID_REQUIRED' => 'คำร้องได้รับอนุมัติและรอบันทึก Course ID',
            'COURSE_ID_RECORDED' => 'บันทึก Course ID เรียบร้อยแล้ว',
            'REQUEST_COMPLETED_REQUESTER' => 'ดำเนินการคำร้องเสร็จสิ้นแล้ว',
            'REQUEST_COMPLETED_FORM_HEAD' => 'ดำเนินการคำร้องเสร็จสิ้นแล้ว (แจ้งหัวหน้าผู้รับผิดชอบแบบฟอร์ม)',
            'REQUEST_COMPLETED_APPROVER' => 'ดำเนินการคำร้องเสร็จสิ้นแล้ว (แจ้งเพื่อทราบ)',
            'REQUEST_RESULT_UPDATED' => 'เจ้าหน้าที่แก้ไขผลการดำเนินการคำร้องแล้ว',
            'STUDENT_ROSTER_SUBMITTED' => 'ผู้ยื่นคำร้องแนบรายชื่อผู้เรียนแล้ว',
            'STUDENT_ROSTER_UPDATED' => 'ผู้ยื่นคำร้องอัปเดตรายชื่อผู้เรียน',
            'STUDENT_ROSTER_REOPEN_REQUESTED' => 'ผู้ยื่นคำร้องขอแก้ไขรายชื่อผู้เรียน',
            'STUDENT_ROSTER_ACKNOWLEDGED' => 'เจ้าหน้าที่รับทราบรายชื่อผู้เรียนแล้ว',
            'STUDENT_ROSTER_REOPENED' => 'เจ้าหน้าที่อนุญาตให้อัปโหลดรายชื่อใหม่',
            default => 'สถานะคำร้องมีการเปลี่ยนแปลง',
        };

        return [
            "[$request->request_no] $action",
            "$action\n\nเลขที่คำร้อง: $request->request_no\nชื่อคำร้อง: $request->request_title"
                .($type === 'DEAN_REJECTED'
                    ? "\nเหตุผลที่ไม่รับรอง: ".($request->deanReviews()->latest('decided_at')->value('comment') ?: '—')
                    : '')
                .($type === 'REQUEST_REJECTED'
                    ? "\nเหตุผลที่ไม่อนุมัติ: ".($request->officerReviews()->latest('reviewed_at')->value('rejection_reason') ?: '—')
                    : '')
                .($type === 'REQUEST_COMPLETED_FORM_HEAD' ? '' : "\nรายละเอียด: $requestUrl"),
            null,
        ];
    }

    private function workflowTemplateMessage(string $type, CourseRequest $request, NotificationOutbox $notification): array
    {
        $context = $this->workflowEmailContext($request);
        $departmentName = $context['department_name'];
        $requestLabel = "$request->form_type_code {$context['service_name']}";
        $actionUrl = null;
        $actionLabel = null;
        $expiresAtText = null;
        $resultText = null;
        $completedAtText = null;

        if ($type === 'DEAN_REVIEW_REQUIRED') {
            $subject = "[รออนุมัติ] คำร้อง {$requestLabel} ของ {$departmentName}";
            $heading = 'มีคำร้องรอการพิจารณารับรอง';
            $greeting = $request->dean_position_name ?: 'คณบดี/ผู้อำนวยการ';
            $intro = "มีคำร้องบริการ{$context['service_name']} ของ {$departmentName} รอการพิจารณาจากท่าน";
            $expiresAt = now()->addMonthsNoOverflow((int) config('course-workflow.dean.review_link_months', 1));
            $expiresAtText = $this->thaiBuddhistDateTime($expiresAt);
            $actionUrl = URL::temporarySignedRoute(
                'dean.review.confirm',
                $expiresAt,
                [
                    'id' => $request->request_id,
                    'notification' => $notification->getKey(),
                ],
            );
            $actionLabel = 'ดูรายละเอียดและพิจารณาคำร้อง';
        } elseif ($type === 'OFFICER_REVIEW_REQUIRED') {
            $subject = "[งานใหม่] คำร้อง {$requestLabel} ของ {$departmentName}";
            $heading = 'มีรายการคำร้องใหม่เข้ามา';
            $greeting = null;
            $intro = 'มีรายการคำร้องใหม่เข้ามา (หัวหน้าส่วนงานอนุมัติแล้ว)';
            $actionUrl = route('officer.show', $request->request_id);
            $actionLabel = 'เปิดคำร้อง';
        } elseif (in_array($type, ['REQUEST_COMPLETED_REQUESTER', 'REQUEST_RESULT_UPDATED'], true)) {
            $subject = "[ดำเนินการเรียบร้อย] คำร้อง {$requestLabel} ของ {$departmentName}";
            $heading = 'ดำเนินการตามคำร้องเรียบร้อยแล้ว';
            $greeting = $this->personNameFor((int) $request->requester_pers_id, 'ผู้ขอรับบริการ');
            $intro = 'สำนักคอมพิวเตอร์ได้ดำเนินการตามคำร้องของท่านเรียบร้อยแล้ว';
            $resultText = $this->executionResultFor($request);
        } else {
            $subject = "[แจ้งเพื่อทราบ] ดำเนินการคำร้อง {$requestLabel} ของ {$departmentName} เรียบร้อย";
            $heading = 'แจ้งผลการดำเนินการคำร้อง';
            $greeting = $this->personNameFor((int) $notification->recipient_pers_id, 'หัวหน้าส่วนงาน');
            $completedAtText = $request->completed_at
                ? $this->thaiBuddhistDateTime($request->completed_at)
                : $this->thaiBuddhistDateTime(now());
            $intro = "ได้ดำเนินการคำร้อง {$requestLabel} ของ {$departmentName} เรียบร้อยแล้ว เมื่อ {$completedAtText}";
        }

        $plainRows = collect($context['rows'])
            ->map(fn ($value, $label) => "$label: $value")
            ->implode("\n");
        $body = ($greeting ? "เรียน {$greeting}\n\n" : '')
            .$intro."\n\n"
            .($resultText ? "ผลการดำเนินการ\n{$resultText}\n\n" : '')
            .$plainRows
            .($actionUrl ? "\n\n{$actionLabel}: {$actionUrl}" : '')
            .($expiresAtText ? "\n\nลิงก์หมดอายุวันที่ {$expiresAtText}" : '');

        $html = view('emails.workflow-notification', [
            'subject' => $subject,
            'heading' => $heading,
            'greeting' => $greeting,
            'intro' => $intro,
            'rows' => $context['rows'],
            'resultText' => $resultText,
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
            'expiresAtText' => $expiresAtText,
        ])->render();

        return [$subject, $body, $html];
    }

    private function workflowEmailContext(CourseRequest $request): array
    {
        $departmentDirectory = app(DepartmentDirectory::class);
        $departmentName = $departmentDirectory->name((int) $request->target_dept_id);
        $subdepartmentName = $request->target_subdept_id
            ? $departmentDirectory->subdepartmentName((int) $request->target_subdept_id)
            : null;
        $unitName = $subdepartmentName ? "$departmentName / $subdepartmentName" : $departmentName;

        [$serviceName, $formName] = match ($request->form_type_code) {
            'CC-ITED-01' => ['ขอใช้พื้นที่เว็บไซต์', 'บริการขอใช้พื้นที่สำหรับจัดทำเว็บไซต์ สำหรับส่วนงาน/โครงการ/ประชุมวิชาการ'],
            'CC-ITED-02' => ['ขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์', 'บริการขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์'],
            'CC-ITED-04' => ['ขอเพิ่มพื้นที่บนเว็บไซต์', 'บริการขอเพิ่มพื้นที่บนเว็บไซต์'],
            'CC-ITED-05' => ['ขอสิทธิ์ผู้ดูแล e-Meeting', 'บริการขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting'],
            default => ['ขอสร้างรายวิชา Moodle Academy', 'บริการขอสร้างรายวิชาในระบบ SWU Moodle Academy'],
        };

        $rows = [
            'เลขที่คำร้อง' => $request->request_no,
            'แบบฟอร์ม' => "$request->form_type_code $formName",
            'ส่วนงาน' => $unitName,
        ];

        if ($request->form_type_code === 'CC-ITED-01') {
            $detail = $request->websiteDetail;
            $rows += [
                'ชื่อเว็บไซต์' => $detail?->website_name ?: '—',
                'โดเมน (URL)' => $detail?->domain_url ?: '—',
                'รายละเอียดเว็บไซต์' => $detail?->platform?->platform_name_th ?: '—',
                'รายละเอียดเพิ่มเติม' => $detail?->additional_detail ?: '—',
                'ผู้ดูแลเว็บไซต์ / ผู้ประสานงาน' => $this->contactText($request->websiteContact),
            ];
        } elseif ($request->form_type_code === 'CC-ITED-02') {
            $detail = $request->websiteContactChangeDetail;
            $rows += [
                'ชื่อเว็บไซต์' => $detail?->website_name ?: '—',
                'โดเมน (URL)' => $detail?->domain_url ?: '—',
                'ผู้ดูแลเว็บไซต์เดิม' => $this->contactText($request->currentWebsiteContact),
                'ผู้ดูแลเว็บไซต์คนใหม่' => $this->contactText($request->newWebsiteContact),
            ];
        } elseif ($request->form_type_code === 'CC-ITED-04') {
            $detail = $request->websiteStorageDetail;
            $rows += [
                'ชื่อเว็บไซต์' => $detail?->website_name ?: '—',
                'โดเมน (URL)' => $detail?->domain_url ?: '—',
                'พื้นที่ที่ขอเพิ่ม' => ($detail?->requested_space_gb ?: 1).' GB',
                'ผู้ดูแลเว็บไซต์ / ผู้ประสานงาน' => $this->contactText($request->websiteContact),
            ];
        } elseif ($request->form_type_code === 'CC-ITED-05') {
            $detail = $request->emeetingDetail;
            $rows += [
                'ขอรับบริการ' => $detail?->action_type === 'CHANGE_CONTACT' ? 'เปลี่ยนแปลงสิทธิ์ผู้ดูแล' : 'ขอสิทธิ์ผู้ดูแล',
            ];
            if ($detail?->action_type === 'CHANGE_CONTACT') {
                $rows['ผู้ดูแลเดิม'] = $this->contactText($request->currentEmeetingContact);
            }
            $rows['ผู้ดูแลที่จะได้รับสิทธิ์'] = $this->contactText($request->emeetingContact);
        } else {
            $instructorNames = $request->instructors
                ->pluck('instructor_name')
                ->filter(fn ($name) => filled($name))
                ->implode(', ');
            $rows += [
                'ชื่อโครงการ' => $request->project_name ?: '—',
                'ชื่อรายวิชา' => $request->course_name_th ?: '—',
                'ระยะเวลาดำเนินกิจกรรม' => $this->thaiBuddhistDate($request->starts_on).' ถึง '.$this->thaiBuddhistDate($request->ends_on),
                'รายชื่ออาจารย์ผู้สอน' => $instructorNames ?: '—',
                'จำนวนผู้เรียนที่คาดการณ์' => $request->expected_students ? $request->expected_students.' คน' : '—',
                'ผู้ประสานงาน' => $this->contactText($request->coordinatorContact),
            ];
        }

        return [
            'department_name' => $departmentName,
            'service_name' => $serviceName,
            'rows' => $rows,
        ];
    }

    private function executionResultFor(CourseRequest $request): string
    {
        return match ($request->form_type_code) {
            'CC-ITED-01' => 'โดเมนที่จัดสรร: '.($request->websiteDetail?->allocated_domain ?: '—'),
            'CC-ITED-02' => $request->websiteContactChangeDetail?->execution_detail ?: 'ดำเนินการเปลี่ยนแปลงผู้ดูแลเว็บไซต์เรียบร้อยแล้ว',
            'CC-ITED-04' => collect([
                $request->websiteStorageDetail?->execution_detail,
                $request->websiteStorageDetail?->granted_space_gb
                    ? 'พื้นที่ที่อนุมัติ: '.$request->websiteStorageDetail->granted_space_gb.' GB'
                    : null,
            ])->filter(fn ($value) => filled($value))->implode("\n") ?: 'ดำเนินการเพิ่มพื้นที่เว็บไซต์เรียบร้อยแล้ว',
            'CC-ITED-05' => $request->emeetingDetail?->execution_detail ?: 'ดำเนินการกำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว',
            default => collect([
                $request->course_id ? 'Course ID: '.$request->course_id : null,
                $request->moodleCourseDetail?->execution_detail,
            ])->filter(fn ($value) => filled($value))->implode("\n") ?: 'ดำเนินการสร้างรายวิชาเรียบร้อยแล้ว',
        };
    }

    private function contactText($contact): string
    {
        if ($contact === null) {
            return '—';
        }

        $details = collect([$contact->email, $contact->phone])
            ->filter(fn ($value) => filled($value))
            ->implode(', ');

        return trim((string) $contact->full_name).($details !== '' ? " ($details)" : '');
    }

    private function personNameFor(int $personId, string $fallback): string
    {
        if ($personId <= 0) {
            return $fallback;
        }

        $directory = app(PersonnelDirectory::class);
        $person = $directory->findByPersonId($personId);

        return $person ? $directory->name($person) : $fallback;
    }

    private function recipientEmailFor(NotificationOutbox $notification): string
    {
        $testEmail = (string) config('course-workflow.notifications.test_recipient_email', '');
        $recipientEmail = $notification->recipient_email;

        if (app()->environment('local', 'uat')
            && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $recipientEmail = $testEmail;
        }

        $recipientEmail = strtolower(trim($recipientEmail));
        if (! str_ends_with($recipientEmail, '@g.swu.ac.th')
            || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Notification recipient must use an @g.swu.ac.th address.');
        }

        return $recipientEmail;
    }

    private function thaiBuddhistDate(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        $localizedDate = \Carbon\Carbon::instance($date)->locale('th');

        return $localizedDate->translatedFormat('j F').' '.($localizedDate->year + 543);
    }

    private function thaiBuddhistDateTime(\DateTimeInterface $date): string
    {
        $localizedDate = \Carbon\Carbon::instance($date)->locale('th');

        return $localizedDate->translatedFormat('j F').' '.($localizedDate->year + 543)
            .' เวลา '.$localizedDate->format('H:i').' น.';
    }
}
