<?php

namespace Tests\Feature;

use Tests\TestCase;

class MoodleCourseExecutionDetailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    public function test_requester_can_see_course_id_and_execution_detail(): void
    {
        $record = [
            'id' => 6,
            'number' => 'SWU-2026-000006',
            'form_type_code' => 'CC-ITED-06',
            'project_name' => 'โครงการทดสอบ',
            'unit' => 'สำนักคอมพิวเตอร์',
            'subunit' => null,
            'course_id' => '6674',
            'execution_detail' => 'สร้างรายวิชาและกำหนดค่าพื้นฐานเรียบร้อยแล้ว',
            'status' => 'COMPLETED',
            'document_name' => 'CC-ITED-06_000006.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'instructors' => [],
        ];

        $html = view('portal.request-detail', compact('record'))->render();

        $this->assertStringContainsString('ผลการสร้างรายวิชา', $html);
        $this->assertStringContainsString('<dt>Course ID</dt>', $html);
        $this->assertStringContainsString('6674', $html);
        $this->assertStringContainsString('<dt>รายละเอียดการดำเนินการ</dt>', $html);
        $this->assertStringContainsString('สร้างรายวิชาและกำหนดค่าพื้นฐานเรียบร้อยแล้ว', $html);
    }

    public function test_officer_approval_requires_course_id_but_execution_detail_is_optional(): void
    {
        $record = [
            'id' => 6,
            'number' => 'SWU-2026-000006',
            'form_type_code' => 'CC-ITED-06',
            'project_name' => 'โครงการทดสอบ',
            'unit' => 'สำนักคอมพิวเตอร์',
            'subunit' => null,
            'status' => 'UNDER_OFFICER_REVIEW',
            'document_name' => 'CC-ITED-06_000006.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_version_count' => 0,
            'instructors' => [],
        ];

        $html = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();
        preg_match('/<textarea[^>]*name="execution_detail"[^>]*>/', $html, $executionDetail);
        preg_match('/<input[^>]*name="course_id"[^>]*>/', $html, $courseId);

        $this->assertStringContainsString('รายละเอียดการดำเนินการ (ถ้ามี)', $html);
        $this->assertStringNotContainsString('required', $executionDetail[0] ?? '');
        $this->assertStringContainsString('required', $courseId[0] ?? '');
    }
}
