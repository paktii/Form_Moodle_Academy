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
    public function test_dean_review_page_does_not_show_student_roster(): void
    {
        $mockRequest = new CourseRequest();
        $mockRequest->request_no = 'CC-TEST-01';
        $mockRequest->status = 'PENDING_DEAN_REVIEW';

        $record = [
            'request_no' => 'CC-TEST-01',
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
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ])->render();

        $this->assertStringNotContainsString('id="dean-student-roster"', $html);
        $this->assertStringNotContainsString('รายชื่อผู้เรียน', $html);
        $this->assertStringNotContainsString('roster-2026.xlsx', $html);
        $this->assertStringNotContainsString('http://localhost/attachment/roster', $html);

        // Document PDF is excluded from attachments list
        $this->assertStringNotContainsString('CC-TEST-01_FORM.pdf', $html);

        // Additional files are displayed in attachments section
        $this->assertStringContainsString('attached-syllabus.pdf', $html);

    }
}
