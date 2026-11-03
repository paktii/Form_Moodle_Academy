<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class WebsiteContactChangeViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag());
    }

    public function test_all_cc_ited_02_form_steps_render_with_the_expected_fields(): void
    {
        $baseData = [
            'formName' => 'แบบฟอร์มขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์',
            'steps' => config('course-workflow.website_contact_change_steps'),
            'departments' => collect([10 => 'คณะวิทยาศาสตร์']),
            'subdepartments' => collect([20 => 'ภาควิชาวิทยาการคอมพิวเตอร์']),
            'subdepartmentParents' => [20 => 10],
            'deanOptions' => [30 => 'ผู้รับรองตัวอย่าง', 713796 => 'นางสาว ภัคธีมา รัตนคช (pakteema@g.swu.ac.th)'],
            'data' => [
                'request_no' => 'SWU-2026-000123',
                'new_contact_first' => 'ผู้ยื่น',
                'new_contact_last' => 'คำร้อง',
                'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
                'new_contact_email' => 'requester@g.swu.ac.th',
                'new_contact_phone' => '15044',
            ],
        ];

        $stepOne = view('portal.website-contact-change-form-steps', $baseData + ['step' => 1])->render();
        $stepTwo = view('portal.website-contact-change-form-steps', $baseData + ['step' => 2])->render();
        $stepThree = view('portal.website-contact-change-form-steps', $baseData + ['step' => 3])->render();

        $this->assertStringContainsString('name="website_name"', $stepOne);
        $this->assertStringContainsString('name="domain_url"', $stepOne);
        $this->assertStringContainsString('name="current_contact_first"', $stepTwo);
        $this->assertStringContainsString('name="new_contact_first"', $stepTwo);
        $this->assertStringContainsString('name="new_contact_email"', $stepTwo);
        $this->assertMatchesRegularExpression('/name="new_contact_first"[^>]*value="ผู้ยื่น"/', $stepTwo);
        $this->assertDoesNotMatchRegularExpression('/name="new_contact_(?:first|email)"[^>]*(?:readonly|disabled)/', $stepTwo);
        $this->assertStringContainsString('สำนักคอมพิวเตอร์จะทำการเปลี่ยนรหัสผ่านของ Account', $stepTwo);
        $this->assertTrue(
            strpos($stepTwo, 'website-certification') < strpos($stepTwo, 'สำนักคอมพิวเตอร์จะทำการเปลี่ยนรหัสผ่านของ Account'),
        );
        $this->assertStringContainsString('name="dean_pers_id"', $stepThree);
        $this->assertStringContainsString('data-searchable-select-show-all-on-focus', $stepThree);
        $this->assertStringContainsString('pakteema@g.swu.ac.th', $stepThree);
        $this->assertStringContainsString('CC-ITED-02_000123.pdf', $stepThree);
    }

    public function test_cc_ited_02_pdf_uses_website_pdf_typography_and_contact_change_fields(): void
    {
        $html = view('portal.website-contact-change-document-pdf', ['record' => [
            'form_type_code' => 'CC-ITED-02',
            'request_no' => 'SWU-2026-000123',
            'unit' => 'คณะวิทยาศาสตร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'new_contact_first' => 'ผู้ดูแล',
            'new_contact_last' => 'ใหม่',
            'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'new_contact_email' => 'new.contact@g.swu.ac.th',
            'new_contact_phone' => '15044',
            'submitted_at' => '2026-10-27',
            'officer_decision' => 'APPROVED',
            'officer_name' => 'เจ้าหน้าที่ทดสอบ',
            'officer_reviewed_at' => '28 ตุลาคม 2569',
            'execution_detail' => 'เปลี่ยนข้อมูลผู้ดูแลเว็บไซต์เรียบร้อยแล้ว',
        ]])->render();

        $this->assertStringContainsString('website-document__header', $html);
        $this->assertStringContainsString('บริการขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์', $html);
        $this->assertStringContainsString('ผู้ดูแล เดิม', $html);
        $this->assertStringContainsString('ผู้ดูแล ใหม่', $html);
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่ดำเนินการ', $html);
        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertStringContainsString('website-staff__domain--allocation', $html);
        $this->assertStringContainsString('เปลี่ยนข้อมูลผู้ดูแลเว็บไซต์เรียบร้อยแล้ว', $html);
        $this->assertSame(3, substr_count($html, 'เจ้าหน้าที่ทดสอบ'));
        $this->assertSame(3, substr_count($html, '28 ตุลาคม 2569'));
        $this->assertStringContainsString('font-size: 13pt', $html);
    }

    public function test_cc_ited_02_rejected_pdf_leaves_the_execution_officer_fields_blank(): void
    {
        $html = view('portal.website-contact-change-document-pdf', ['record' => [
            'form_type_code' => 'CC-ITED-02',
            'submitted_at' => '2026-10-27',
            'officer_decision' => 'REJECTED',
            'officer_name' => 'เจ้าหน้าที่ทดสอบ',
            'officer_reviewed_at' => '28 ตุลาคม 2569',
            'reason' => 'ข้อมูลผู้ดูแลไม่ถูกต้อง',
        ]])->render();

        $this->assertMatchesRegularExpression('/document-checkbox is-checked[^>]*><\/span> ไม่อนุมัติ/', $html);
        $this->assertStringContainsString('ข้อมูลผู้ดูแลไม่ถูกต้อง', $html);
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่ดำเนินการ', $html);
        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertSame(2, substr_count($html, 'เจ้าหน้าที่ทดสอบ'));
        $this->assertSame(2, substr_count($html, '28 ตุลาคม 2569'));
    }

    public function test_cc_ited_02_details_separate_current_and_new_contacts_into_sections(): void
    {
        $html = view('components.request-data', ['record' => [
            'form_type_code' => 'CC-ITED-02',
            'unit' => 'คณะวิทยาศาสตร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'new_contact_first' => 'ผู้ดูแล',
            'new_contact_last' => 'ใหม่',
            'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'new_contact_email' => 'new.contact@g.swu.ac.th',
            'new_contact_phone' => '15044',
        ]])->render();

        $this->assertStringContainsString('<h3>ข้อมูลผู้ดูแลเว็บไซต์เดิม</h3>', $html);
        $this->assertStringContainsString('<h3>ข้อมูลผู้ดูแลเว็บไซต์คนใหม่</h3>', $html);
        $this->assertStringNotContainsString('<dt>ผู้ดูแลเว็บไซต์เดิม</dt>', $html);
        $this->assertStringNotContainsString('<dt>ผู้ดูแลเว็บไซต์คนใหม่</dt>', $html);
        $this->assertSame(2, substr_count($html, '<dt>ชื่อ-นามสกุล</dt>'));
    }

    public function test_cc_ited_02_requester_details_show_officer_execution_result(): void
    {
        $record = [
            'id' => 2,
            'number' => 'SWU-2026-000002',
            'form_type_code' => 'CC-ITED-02',
            'project_name' => 'เว็บไซต์ตัวอย่าง',
            'unit' => 'คณะวิทยาศาสตร์',
            'subunit' => 'ภาควิชาวิทยาการคอมพิวเตอร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'new_contact_first' => 'ผู้ดูแล',
            'new_contact_last' => 'ใหม่',
            'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'new_contact_email' => 'new.contact@g.swu.ac.th',
            'new_contact_phone' => '15044',
            'execution_detail' => 'เปลี่ยนรหัสผ่าน Account และยกเลิกสิทธิ์ผู้ดูแลเว็บไซต์เดิมเรียบร้อยแล้ว',
            'status' => 'COMPLETED',
            'document_name' => 'CC-ITED-02_000002.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
        ];

        $html = view('portal.request-detail', compact('record'))->render();

        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertStringContainsString('ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์:', $html);
        $this->assertStringContainsString('เปลี่ยนรหัสผ่าน Account และยกเลิกสิทธิ์ผู้ดูแลเว็บไซต์เดิมเรียบร้อยแล้ว', $html);
        $this->assertStringNotContainsString('ผลการสร้างรายวิชา', $html);
        $this->assertStringNotContainsString('Course ID:', $html);

        $record['form_type_code'] = 'CC-ITED-06';
        $courseHtml = view('portal.request-detail', compact('record'))->render();

        $this->assertStringContainsString('ผลการสร้างรายวิชา', $courseHtml);
        $this->assertStringContainsString('Course ID:', $courseHtml);
        $this->assertStringContainsString('รายละเอียดการดำเนินการ', $courseHtml);
    }

    public function test_cc_ited_02_officer_approval_requires_execution_detail(): void
    {
        $record = [
            'id' => 2,
            'number' => 'SWU-2026-000002',
            'form_type_code' => 'CC-ITED-02',
            'project_name' => 'เว็บไซต์ตัวอย่าง',
            'unit' => 'คณะวิทยาศาสตร์',
            'subunit' => 'ภาควิชาวิทยาการคอมพิวเตอร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'new_contact_first' => 'ผู้ดูแล',
            'new_contact_last' => 'ใหม่',
            'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'new_contact_email' => 'new.contact@g.swu.ac.th',
            'new_contact_phone' => '15044',
            'status' => 'UNDER_OFFICER_REVIEW',
            'document_name' => 'CC-ITED-02_000002.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_version_count' => 0,
        ];

        $html = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();

        $this->assertStringContainsString('name="execution_detail"', $html);
        $this->assertStringContainsString('data-officer-execution-detail', $html);
        $this->assertStringContainsString('ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์', $html);
        $this->assertStringContainsString('placeholder="ระบุรายละเอียดการดำเนินการที่เสร็จสิ้นแล้ว"', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="execution_detail"[^>]*required/', $html);
        $this->assertStringContainsString('อนุมัติและบันทึกข้อมูลการดำเนินการ', $html);

        $record['form_type_code'] = 'CC-ITED-06';
        $courseHtml = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();
        preg_match('/<button[^>]*data-officer-approval-send[^>]*>/', $courseHtml, $courseApprovalButton);

        $this->assertStringContainsString('name="execution_detail"', $courseHtml);
        $this->assertStringContainsString('รายละเอียดการดำเนินการ', $courseHtml);
        $this->assertStringContainsString('อนุมัติและบันทึกผลการดำเนินการ', $courseHtml);
        $this->assertStringNotContainsString('disabled', $courseApprovalButton[0] ?? '');
    }

    public function test_officer_can_edit_a_completed_cc_ited_02_result_and_choose_email_notification(): void
    {
        $record = [
            'id' => 2,
            'number' => 'SWU-2026-000002',
            'form_type_code' => 'CC-ITED-02',
            'project_name' => 'เว็บไซต์ตัวอย่าง',
            'unit' => 'คณะวิทยาศาสตร์',
            'subunit' => 'ภาควิชาวิทยาการคอมพิวเตอร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'new_contact_first' => 'ผู้ดูแล',
            'new_contact_last' => 'ใหม่',
            'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'new_contact_email' => 'new.contact@g.swu.ac.th',
            'new_contact_phone' => '15044',
            'execution_detail' => 'ดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์เรียบร้อยแล้ว',
            'status' => 'COMPLETED',
            'document_name' => 'CC-ITED-02_000002.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_version_count' => 0,
        ];

        $html = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();

        $this->assertStringContainsString('data-open-dialog="edit-result-dialog"', $html);
        $this->assertStringContainsString('action="'.route('officer.result.update', 2).'"', $html);
        $this->assertStringContainsString('name="execution_detail"', $html);
        $this->assertStringContainsString('ดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์เรียบร้อยแล้ว', $html);
        $this->assertStringContainsString('name="notify_requester"', $html);
        $this->assertStringContainsString('ส่งอีเมลแจ้งเตือนผู้ยื่นคำร้อง', $html);
        $this->assertStringContainsString('data-edit-result-form', $html);
        $this->assertStringContainsString('data-edit-result-entry', $html);
        $this->assertStringContainsString('data-edit-result-confirm', $html);
        $this->assertStringContainsString('data-edit-result-cancel', $html);
        $this->assertStringContainsString('data-edit-result-save', $html);
        $this->assertStringContainsString('data-edit-result-confirm-back', $html);
        $this->assertStringContainsString('data-edit-result-confirm-action', $html);
    }

    public function test_cc_ited_02_cannot_be_approved_without_execution_detail(): void
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
                'request_id' => 2,
                'form_type_code' => 'CC-ITED-02',
            ]);

            $response = $this->withSession(['portal.actor' => [
                'pers_id' => 4001,
                'dept_id' => 200,
                'buasri_id' => 'officer',
                'name' => 'เจ้าหน้าที่ทดสอบ',
                'email' => 'officer@g.swu.ac.th',
                'label' => 'เจ้าหน้าที่',
                'role' => 'officer',
            ]])->from('/officer/reviews/2')->post('/officer/reviews/2', [
                'decision' => 'pass',
            ]);

            $response
                ->assertRedirect('/officer/reviews/2')
                ->assertSessionHasErrors([
                    'execution_detail' => 'กรุณาระบุข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์',
                ]);
        } finally {
            DB::purge('course133');
        }
    }

    public function test_cc_ited_02_rejects_the_same_current_and_new_website_contact(): void
    {
        $response = $this->withSession(['portal.actor' => [
            'pers_id' => 100,
            'dept_id' => 200,
            'buasri_id' => 'requester',
            'name' => 'ผู้ยื่นคำร้องทดสอบ',
            'email' => 'requester@g.swu.ac.th',
            'label' => 'บุคลากร',
            'role' => 'user',
        ]])->from('/request/website-contact-change/create/2')->post('/request/website-contact-change/create/2', [
            'current_contact_first' => 'Pakteema',
            'current_contact_last' => 'Rattanakhot',
            'new_contact_first' => '  pakteema ',
            'new_contact_last' => ' rattanakhot  ',
            'new_contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'new_contact_email' => 'pakteema@g.swu.ac.th',
            'new_contact_phone' => '0864710612',
        ]);

        $response
            ->assertRedirect('/request/website-contact-change/create/2')
            ->assertSessionHasErrors([
                'new_contact_first' => 'ผู้ดูแลเว็บไซต์คนใหม่ต้องไม่เป็นบุคคลเดียวกับผู้ดูแลเว็บไซต์เดิม',
            ]);
        $this->assertFalse(session()->has('portal.website_contact_change_completed.2'));
    }
}
