<?php

namespace Tests\Feature;

use Tests\TestCase;

class WorkflowNotificationEmailTest extends TestCase
{
    public function test_dean_email_keeps_the_detail_and_review_button(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[รออนุมัติ] คำร้อง CC-ITED-01 ขอใช้พื้นที่เว็บไซต์ ของ สำนักคอมพิวเตอร์',
            'heading' => 'มีคำร้องรอการพิจารณารับรอง',
            'greeting' => 'คณบดี',
            'intro' => 'มีคำร้องรอการพิจารณาจากท่าน',
            'rows' => [
                'เลขที่คำร้อง' => 'REQ-001',
                'แบบฟอร์ม' => 'CC-ITED-01 บริการขอใช้พื้นที่สำหรับจัดทำเว็บไซต์',
            ],
            'resultText' => null,
            'actionUrl' => 'https://example.test/dean/reviews/1',
            'actionLabel' => 'ดูรายละเอียดและพิจารณาคำร้อง',
            'expiresAtText' => '30 พฤศจิกายน 2569 เวลา 12:00 น.',
        ])->render();

        $this->assertStringContainsString('ดูรายละเอียดและพิจารณาคำร้อง', $html);
        $this->assertStringContainsString('https://example.test/dean/reviews/1', $html);
        $this->assertStringContainsString('REQ-001', $html);
    }

    public function test_requester_email_shows_execution_result_without_an_action_button(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[ดำเนินการเรียบร้อย] คำร้อง CC-ITED-06 ขอสร้างรายวิชา Moodle Academy',
            'heading' => 'ดำเนินการตามคำร้องเรียบร้อยแล้ว',
            'greeting' => 'ผู้ยื่นคำร้อง',
            'intro' => 'สำนักคอมพิวเตอร์ได้ดำเนินการตามคำร้องของท่านเรียบร้อยแล้ว',
            'rows' => ['เลขที่คำร้อง' => 'REQ-002'],
            'resultText' => "Course ID: 12345\nสร้างรายวิชาเรียบร้อยแล้ว",
            'actionUrl' => null,
            'actionLabel' => null,
            'expiresAtText' => null,
        ])->render();

        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertStringContainsString('Course ID: 12345', $html);
        $this->assertStringNotContainsString('<a href=', $html);
    }

    public function test_dean_email_can_omit_the_heading(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[รออนุมัติ] คำร้องเว็บไซต์',
            'heading' => null,
            'greeting' => 'คณบดี',
            'intro' => 'มีคำร้องบริการขอใช้พื้นที่เว็บไซต์ สำหรับโครงการ ของ คณะตัวอย่าง รอการพิจารณาจากท่าน',
            'rows' => ['เลขที่คำร้อง' => 'REQ-003'],
            'resultText' => null,
            'actionUrl' => 'https://example.test/dean/reviews/3',
            'actionLabel' => 'ดูรายละเอียดและพิจารณาคำร้อง',
            'expiresAtText' => null,
        ])->render();

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('สำหรับโครงการ', $html);
    }

    public function test_officer_email_omits_the_heading_but_keeps_the_intro(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[งานใหม่] คำร้องเว็บไซต์',
            'heading' => null,
            'greeting' => null,
            'intro' => 'มีรายการคำร้องใหม่เข้ามา (หัวหน้าส่วนงานอนุมัติแล้ว)',
            'rows' => ['เลขที่คำร้อง' => 'REQ-006'],
            'resultText' => null,
            'actionUrl' => 'https://example.test/officer/requests/6',
            'actionLabel' => 'เปิดคำร้อง',
            'expiresAtText' => null,
        ])->render();

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('มีรายการคำร้องใหม่เข้ามา (หัวหน้าส่วนงานอนุมัติแล้ว)', $html);
        $this->assertStringContainsString('เปิดคำร้อง', $html);
    }

    public function test_rejection_email_shows_the_rejection_reason_in_danger_style(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[ไม่อนุมัติ] คำร้องเว็บไซต์',
            'heading' => null,
            'greeting' => 'ผู้ยื่นคำร้อง',
            'intro' => 'เจ้าหน้าที่ไม่อนุมัติคำร้องขอใช้พื้นที่สำหรับจัดทำเว็บไซต์ สำหรับโครงการของท่าน',
            'rows' => ['เลขที่คำร้อง' => 'REQ-004'],
            'resultText' => 'ข้อมูลประกอบคำร้องไม่ครบถ้วน',
            'resultTitle' => 'เหตุผลที่ไม่อนุมัติ',
            'resultTone' => 'danger',
            'actionUrl' => null,
            'actionLabel' => null,
            'expiresAtText' => null,
        ])->render();

        $this->assertStringNotContainsString('คำร้องไม่ได้รับการอนุมัติ', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringContainsString('สำหรับโครงการ', $html);
        $this->assertStringContainsString('เหตุผลที่ไม่อนุมัติ', $html);
        $this->assertStringContainsString('ข้อมูลประกอบคำร้องไม่ครบถ้วน', $html);
        $this->assertStringContainsString('#fef2f2', $html);
        $this->assertStringContainsString('align="center"', $html);
        $this->assertStringContainsString('overflow-wrap:anywhere', $html);
    }

    public function test_form_head_email_omits_heading_and_places_completion_time_on_a_new_line(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[แจ้งเพื่อทราบ] ดำเนินการคำร้องเรียบร้อย',
            'heading' => null,
            'greeting' => 'หัวหน้าส่วนงาน',
            'intro' => "ได้ดำเนินการคำร้องเรียบร้อยแล้ว\nเมื่อ 4 พฤศจิกายน 2569 เวลา 11:10 น.",
            'rows' => ['เลขที่คำร้อง' => 'REQ-005'],
            'resultText' => null,
            'actionUrl' => null,
            'actionLabel' => null,
            'expiresAtText' => null,
        ])->render();

        $this->assertStringNotContainsString('แจ้งผลการดำเนินการคำร้อง', $html);
        $this->assertStringNotContainsString('<h1', $html);
        $this->assertMatchesRegularExpression('/เรียบร้อยแล้ว<br\s*\/?>\s*เมื่อ 4 พฤศจิกายน 2569/', $html);
    }

    public function test_student_roster_acknowledgement_uses_the_shared_email_template(): void
    {
        $html = view('emails.workflow-notification', [
            'subject' => '[รับทราบรายชื่อผู้เรียน] คำร้อง Moodle Academy',
            'heading' => null,
            'greeting' => 'ผู้ยื่นคำร้อง',
            'intro' => 'เจ้าหน้าที่รับทราบรายชื่อผู้เรียนสำหรับคำร้องของท่านแล้ว ระบบล็อกการแก้ไขไฟล์รายชื่อผู้เรียนเรียบร้อยแล้ว',
            'rows' => [
                'เลขที่คำร้อง' => 'REQ-006',
                'แบบฟอร์ม' => 'CC-ITED-06 บริการขอสร้างรายวิชาในระบบ SWU Moodle Academy',
            ],
            'resultText' => null,
            'actionUrl' => 'https://example.test/request/6',
            'actionLabel' => 'ดูรายละเอียดคำร้อง',
            'expiresAtText' => null,
        ])->render();

        $this->assertStringContainsString('แบบฟอร์มขอรับบริการ สำนักคอมพิวเตอร์', $html);
        $this->assertStringContainsString('ดูรายละเอียดคำร้อง', $html);
        $this->assertStringContainsString('REQ-006', $html);
    }

    public function test_all_student_roster_notifications_use_the_shared_email_layout(): void
    {
        $cases = [
            ['ผู้ยื่นคำร้องได้แนบรายชื่อผู้เรียนแล้ว', 'เปิดคำร้อง', 'https://example.test/officer/requests/7'],
            ['ผู้ยื่นคำร้องได้อัปเดตรายชื่อผู้เรียนแล้ว', 'เปิดคำร้อง', 'https://example.test/officer/requests/7'],
            ['ผู้ยื่นคำร้องขอแก้ไขรายชื่อผู้เรียน', 'เปิดคำร้อง', 'https://example.test/officer/requests/7'],
            ['เจ้าหน้าที่อนุญาตให้ท่านอัปโหลดรายชื่อผู้เรียนใหม่แล้ว', 'ดูรายละเอียดคำร้อง', 'https://example.test/request/7'],
        ];

        foreach ($cases as [$intro, $actionLabel, $actionUrl]) {
            $html = view('emails.workflow-notification', [
                'subject' => '[แจ้งเตือนรายชื่อผู้เรียน] คำร้อง Moodle Academy',
                'heading' => null,
                'greeting' => $actionLabel === 'ดูรายละเอียดคำร้อง' ? 'ผู้ยื่นคำร้อง' : null,
                'intro' => $intro,
                'rows' => [
                    'เลขที่คำร้อง' => 'REQ-007',
                    'แบบฟอร์ม' => 'CC-ITED-06 บริการขอสร้างรายวิชาในระบบ SWU Moodle Academy',
                ],
                'resultText' => null,
                'actionUrl' => $actionUrl,
                'actionLabel' => $actionLabel,
                'expiresAtText' => null,
            ])->render();

            $this->assertStringContainsString('แบบฟอร์มขอรับบริการ สำนักคอมพิวเตอร์', $html);
            $this->assertStringContainsString($intro, $html);
            $this->assertStringContainsString($actionLabel, $html);
            $this->assertStringContainsString($actionUrl, $html);
            $this->assertStringContainsString('REQ-007', $html);
            $this->assertStringNotContainsString('<h1', $html);
        }
    }
}
