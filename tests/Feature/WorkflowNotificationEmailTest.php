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
}
