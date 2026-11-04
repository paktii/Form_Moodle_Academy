<?php

namespace Tests\Feature;

use App\Models\CourseRequest;
use Tests\TestCase;

class DeanReviewPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
    }
    public function test_dean_review_page_shows_student_roster_with_other_attachments(): void
    {
        $mockRequest = new CourseRequest();
        $mockRequest->request_no = 'CC-TEST-01';
        $mockRequest->status = 'PENDING_DEAN_REVIEW';

        $record = [
            'request_no' => 'CC-TEST-01',
            'form_type_code' => 'CC-ITED-06',
            'document_name' => 'CC-TEST-01_FORM.pdf',
            'requires_student_roster' => true,
            'has_student_roster' => true,
            'student_roster_name' => 'roster-2026.xlsx',
            'student_roster_version_count' => 1,
            'student_roster_uploaded_at' => '14 ตุลาคม 2569',
            'student_roster_acknowledged' => false,
            'additional_files' => [
                ['document_id' => 99, 'additional_name' => 'attached-syllabus.pdf'],
            ],
            'instructors' => [],
        ];

        $html = view('portal.dean-review', [
            'courseRequest' => $mockRequest,
            'record' => $record,
            'decisionUrl' => 'http://localhost/decide',
            'documentUrl' => 'http://localhost/document',
            'additionalDocumentUrls' => [99 => 'http://localhost/attachment/99'],
            'studentRosterUrl' => 'http://localhost/attachment/roster',
            'alreadyProcessed' => false,
            'linkInvalidated' => false,
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('id="dean-student-roster"', $html);
        $this->assertStringContainsString('roster-2026.xlsx', $html);
        $this->assertStringContainsString('http://localhost/attachment/roster', $html);
        $this->assertStringContainsString('ดาวน์โหลดไฟล์รายชื่อผู้เรียน roster-2026.xlsx', $html);
        $this->assertStringContainsString('SWU Moodle Academy', $html);

        // Document PDF is excluded from attachments list
        $this->assertStringNotContainsString('CC-TEST-01_FORM.pdf', $html);

        // Additional files are displayed in attachments section
        $this->assertStringContainsString('attached-syllabus.pdf', $html);

    }

    public function test_invalidated_dean_review_link_hides_request_actions_and_documents(): void
    {
        $mockRequest = new CourseRequest();
        $mockRequest->request_no = 'CC-TEST-02';
        $mockRequest->status = 'PENDING_DEAN_REVIEW';

        $html = view('portal.dean-review', [
            'courseRequest' => $mockRequest,
            'record' => [
                'request_no' => 'CC-TEST-02',
                'form_type_code' => 'CC-ITED-06',
                'additional_files' => [],
                'instructors' => [],
            ],
            'decisionUrl' => 'http://localhost/decide',
            'documentUrl' => 'http://localhost/document',
            'additionalDocumentUrls' => [],
            'studentRosterUrl' => null,
            'alreadyProcessed' => false,
            'linkInvalidated' => true,
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ])->render();

        $this->assertStringContainsString('ลิงก์นี้ถูกยกเลิกแล้ว', $html);
        $this->assertStringContainsString('กรุณาใช้ลิงก์จากอีเมลฉบับล่าสุด', $html);
        $this->assertStringNotContainsString('http://localhost/decide', $html);
        $this->assertStringNotContainsString('รายละเอียดคำร้องเพื่อพิจารณารับรอง', $html);
    }
}
