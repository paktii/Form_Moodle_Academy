<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    public function test_requester_can_download_draft_as_a_generated_pdf(): void
    {
        $response = $this->withSession([
            'portal.actor' => [
                ...config('course-workflow.actors.user'),
                'role' => 'user',
            ],
            'portal.draft' => [
                'number' => 'ACA-TEST-001',
                'unit' => 'สำนักคอมพิวเตอร์',
                'project_name' => 'โครงการทดสอบ',
                'project_type' => 'OTHER',
                'project_other' => 'โครงการภายใน',
                'coordinator_first' => 'สมชาย',
                'coordinator_last' => 'ใจดี',
                'coordinator_position' => 'นักวิชาการศึกษา',
                'coordinator_phone' => '02-649-5000',
                'coordinator_email' => 'requester@g.swu.ac.th',
                'course_th' => 'รายวิชาทดสอบ',
                'course_en' => 'Test Course',
                'category' => 'GENERAL',
                'description' => 'รายละเอียดรายวิชาทดสอบ',
                'instructors' => [[
                    'first' => 'อาจารย์สมหญิง',
                    'last' => 'รักการสอน',
                    'email' => 'teacher@g.swu.ac.th',
                ]],
                'learning' => 'แบบกำหนดช่วงเวลาเรียน',
                'starts_at' => '2026-10-01',
                'ends_at' => '2026-12-31',
                'enrollment' => 'สมัครด้วยตนเอง',
                'expected_students' => 100,
            ],
        ])->get('/documents/draft/download');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload('course-request-aca-test-001.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
