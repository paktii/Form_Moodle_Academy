<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class WebsiteStorageViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag());
    }

    public function test_all_cc_ited_04_form_steps_render_with_expected_fields(): void
    {
        $baseData = [
            'formName' => 'แบบฟอร์มขอเพิ่มพื้นที่บนเว็บไซต์',
            'steps' => config('course-workflow.website_storage_steps'),
            'departments' => collect([10 => 'คณะวิทยาศาสตร์']),
            'subdepartments' => collect([20 => 'ภาควิชาวิทยาการคอมพิวเตอร์']),
            'subdepartmentParents' => [20 => 10],
            'deanOptions' => [30 => 'ผู้รับรองตัวอย่าง'],
            'data' => [
                'request_no' => 'SR-2026-000123',
                'contact_first' => 'ผู้ยื่น',
                'contact_last' => 'คำร้อง',
                'contact_position' => 'นักวิชาการคอมพิวเตอร์',
                'contact_email' => 'requester@g.swu.ac.th',
                'contact_phone' => '15044',
            ],
        ];

        $stepOne = view('portal.website-storage-form-steps', $baseData + ['step' => 1])->render();
        $stepTwo = view('portal.website-storage-form-steps', $baseData + ['step' => 2])->render();
        $stepThree = view('portal.website-storage-form-steps', $baseData + ['step' => 3])->render();

        $this->assertStringContainsString('name="website_name"', $stepOne);
        $this->assertStringContainsString('name="domain_url"', $stepOne);
        $this->assertStringContainsString('<strong>1 GB</strong>', $stepOne);
        $this->assertStringContainsString('name="contact_first"', $stepTwo);
        $this->assertStringContainsString('name="contact_email"', $stepTwo);
        $this->assertStringContainsString('ส่วนที่ 2 : ข้อมูลผู้ดูแลเว็บไซต์', $stepTwo);
        $this->assertStringNotContainsString('ส่วนที่ 2 : ข้อมูลผู้ดูแลเว็บไซต์และคำรับรอง', $stepTwo);
        $this->assertStringContainsString('<span class="stepper__text">ข้อมูลผู้ดูแลเว็บไซต์</span>', $stepTwo);
        $this->assertStringNotContainsString('ข้อมูลผู้ดูแลเว็บไซต์และคำรับรอง', $stepTwo);
        $this->assertMatchesRegularExpression('/name="contact_first"[^>]*value="ผู้ยื่น"/', $stepTwo);
        $this->assertMatchesRegularExpression('/name="contact_email"[^>]*value="requester@g\.swu\.ac\.th"/', $stepTwo);
        $this->assertDoesNotMatchRegularExpression('/name="contact_(?:first|email)"[^>]*(?:readonly|disabled)/', $stepTwo);
        $this->assertStringContainsString('name="dean_pers_id"', $stepThree);
        $this->assertStringContainsString('CC-ITED-04_000123.pdf', $stepThree);
    }

    public function test_cc_ited_04_pdf_and_details_show_storage_fields(): void
    {
        $record = [
            'form_type_code' => 'CC-ITED-04',
            'request_no' => 'SR-2026-000123',
            'unit' => 'คณะวิทยาศาสตร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'requested_space_gb' => '1.00',
            'granted_space_gb' => '1.00',
            'execution_detail' => 'เพิ่มพื้นที่เว็บไซต์เรียบร้อยแล้ว',
            'contact_first' => 'ผู้ดูแล',
            'contact_last' => 'เว็บไซต์',
            'contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'contact_email' => 'contact@g.swu.ac.th',
            'contact_phone' => '15044',
            'submitted_at' => '2026-10-27',
            'officer_decision' => 'APPROVED',
            'officer_name' => 'เจ้าหน้าที่ทดสอบ',
            'officer_reviewed_at' => '28 ตุลาคม 2569',
        ];

        $pdfHtml = view('portal.website-storage-document-pdf', compact('record'))->render();
        $detailHtml = view('components.request-data', compact('record'))->render();

        $this->assertStringContainsString('บริการขอเพิ่มพื้นที่สำหรับจัดทำเว็บไซต์', $pdfHtml);
        $this->assertStringContainsString('ต่อครั้งการขอเพิ่มพื้นที่ จำนวน <strong>1 GB</strong>', $pdfHtml);
        $this->assertStringContainsString('<span>ตำแหน่ง</span>', $pdfHtml);
        $this->assertStringContainsString('<span>E-mail</span>', $pdfHtml);
        $this->assertStringContainsString('<span>โทรศัพท์</span>', $pdfHtml);
        $this->assertStringNotContainsString('text-align: right; padding-right: 1.25mm', $pdfHtml);
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่ดำเนินการ', $pdfHtml);
        $this->assertStringContainsString('ผลการดำเนินการ', $pdfHtml);
        $this->assertStringContainsString('website-staff__domain--allocation', $pdfHtml);
        $this->assertStringContainsString('เพิ่มพื้นที่เว็บไซต์เรียบร้อยแล้ว', $pdfHtml);
        $this->assertStringNotContainsString('<b>พื้นที่ที่เพิ่มให้</b>', $pdfHtml);
        $this->assertSame(3, substr_count($pdfHtml, 'เจ้าหน้าที่ทดสอบ'));
        $this->assertSame(3, substr_count($pdfHtml, '28 ตุลาคม 2569'));
        $this->assertStringContainsString('<dt>พื้นที่ที่ขอเพิ่ม</dt>', $detailHtml);
        $this->assertStringContainsString('<dd>1 GB</dd>', $detailHtml);
    }

    public function test_cc_ited_04_rejected_pdf_leaves_the_execution_officer_fields_blank(): void
    {
        $html = view('portal.website-storage-document-pdf', ['record' => [
            'form_type_code' => 'CC-ITED-04',
            'submitted_at' => '2026-10-27',
            'officer_decision' => 'REJECTED',
            'officer_name' => 'เจ้าหน้าที่ทดสอบ',
            'officer_reviewed_at' => '28 ตุลาคม 2569',
            'reason' => 'ข้อมูลพื้นที่ไม่ถูกต้อง',
        ]])->render();

        $this->assertMatchesRegularExpression('/document-checkbox is-checked[^>]*><\/span> ไม่อนุมัติ/', $html);
        $this->assertStringContainsString('ข้อมูลพื้นที่ไม่ถูกต้อง', $html);
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่ดำเนินการ', $html);
        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertSame(2, substr_count($html, 'เจ้าหน้าที่ทดสอบ'));
        $this->assertSame(2, substr_count($html, '28 ตุลาคม 2569'));
    }

    public function test_cc_ited_04_officer_review_requires_execution_detail_and_locks_granted_space(): void
    {
        $record = [
            'id' => 4,
            'number' => 'SR-2026-000004',
            'form_type_code' => 'CC-ITED-04',
            'project_name' => 'เว็บไซต์ตัวอย่าง',
            'unit' => 'คณะวิทยาศาสตร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'requested_space_gb' => '1.00',
            'contact_first' => 'ผู้ดูแล',
            'contact_last' => 'เว็บไซต์',
            'contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'contact_email' => 'contact@g.swu.ac.th',
            'contact_phone' => '15044',
            'status' => 'UNDER_OFFICER_REVIEW',
            'document_name' => 'CC-ITED-04_000004.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_version_count' => 0,
        ];

        $html = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();

        $this->assertMatchesRegularExpression('/<textarea[^>]*name="execution_detail"[^>]*required/', $html);
        $this->assertStringContainsString('ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์', $html);
        $this->assertStringContainsString('name="granted_space_gb"', $html);
        $this->assertStringContainsString('พื้นที่เว็บไซต์ที่เพิ่มให้ (GB)', $html);
        $this->assertMatchesRegularExpression('/name="granted_space_gb"[^>]*value="1"[^>]*class="[^"]*form-field--display-only[^"]*"[^>]*disabled/', $html);
        $this->assertStringContainsString('พื้นที่ถูกกำหนดไว้ที่ 1 GB ไม่สามารถแก้ไขได้', $html);
        $this->assertStringContainsString('อนุมัติและบันทึกข้อมูลการดำเนินการ', $html);
        $this->assertStringNotContainsString('name="course_id"', $html);
        $detailTemplate = file_get_contents(resource_path('views/portal/detail.blade.php'));
        $this->assertStringContainsString('พื้นที่เว็บไซต์ที่เพิ่มให้: 1 GB<br>ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์:', $detailTemplate);
        $this->assertStringContainsString('<div class="document-row execution-detail-row"><x-portal-icon name="globe-code" /><span class="document-row__name">พื้นที่เว็บไซต์ที่เพิ่มให้: 1 GB<br>ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์: <span class="execution-detail-text">', $detailTemplate);

        $requesterHtml = view('portal.request-detail', compact('record'))->render();
        $spacePosition = strpos($requesterHtml, '<dt>พื้นที่เว็บไซต์ที่เพิ่มให้</dt>');
        $executionPosition = strpos($requesterHtml, '<dt>ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์</dt>');
        $this->assertNotFalse($spacePosition);
        $this->assertNotFalse($executionPosition);
        $this->assertTrue($spacePosition < $executionPosition);
        $this->assertStringContainsString('รอเจ้าหน้าที่ดำเนินการ', $requesterHtml);
        $this->assertStringNotContainsString('@elseif', $requesterHtml);
        $this->assertStringNotContainsString('@else', $requesterHtml);
    }

    public function test_cc_ited_04_cannot_be_approved_without_execution_detail(): void
    {
        config()->set('database.connections.course133', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('course133');

        try {
            $database = DB::connection('course133');
            $database->statement("ATTACH DATABASE ':memory:' AS service_portal");
            $database->statement('CREATE TABLE service_portal.service_request (request_id INTEGER PRIMARY KEY, form_type_code TEXT NOT NULL)');
            $database->table('service_portal.service_request')->insert([
                'request_id' => 4,
                'form_type_code' => 'CC-ITED-04',
            ]);

            $response = $this->withSession(['portal.actor' => [
                'pers_id' => 4001,
                'dept_id' => 200,
                'buasri_id' => 'officer',
                'name' => 'เจ้าหน้าที่ทดสอบ',
                'email' => 'officer@g.swu.ac.th',
                'label' => 'เจ้าหน้าที่',
                'role' => 'officer',
            ]])->from('/officer/reviews/4')->post('/officer/reviews/4', [
                'decision' => 'pass',
                'granted_space_gb' => 99,
            ]);

            $response
                ->assertRedirect('/officer/reviews/4')
                ->assertSessionHasErrors([
                    'execution_detail' => 'กรุณาระบุข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์',
                ]);
        } finally {
            DB::purge('course133');
        }
    }
}
