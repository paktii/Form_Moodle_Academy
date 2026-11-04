<?php

namespace Tests\Feature;

use App\Services\DepartmentDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class EmeetingViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag());
    }

    public function test_all_cc_ited_05_form_steps_render_expected_fields(): void
    {
        $baseData = [
            'formName' => 'แบบฟอร์มขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting',
            'steps' => config('course-workflow.emeeting_steps'),
            'departments' => collect([10 => 'คณะวิทยาศาสตร์']),
            'subdepartments' => collect([20 => 'ภาควิชาวิทยาการคอมพิวเตอร์']),
            'subdepartmentParents' => [20 => 10],
            'deanOptions' => [30 => 'ผู้รับรองตัวอย่าง'],
            'data' => [
                'request_no' => 'SWU-2026-000125',
                'action_type' => 'CHANGE_CONTACT',
                'contact_first' => 'ผู้ดูแล',
                'contact_last' => 'ใหม่',
                'contact_position' => 'นักวิชาการคอมพิวเตอร์',
                'contact_email' => 'meeting@g.swu.ac.th',
                'contact_phone' => '17977',
            ],
        ];

        $stepOne = view('portal.emeeting-form-steps', $baseData + ['step' => 1])->render();
        $stepTwo = view('portal.emeeting-form-steps', $baseData + ['step' => 2])->render();
        $stepThree = view('portal.emeeting-form-steps', $baseData + ['step' => 3])->render();
        $newAccessData = $baseData;
        $newAccessData['data']['action_type'] = 'NEW_ACCESS';
        $newAccessData['data']['current_contact_first'] = 'ค่าที่ต้องล้าง';
        $newAccessData['data']['current_contact_last'] = 'ค่าที่ต้องล้าง';
        $newAccessStepOne = view('portal.emeeting-form-steps', $newAccessData + ['step' => 1])->render();
        $unselectedData = $baseData;
        unset($unselectedData['data']['action_type']);
        $unselectedStepOne = view('portal.emeeting-form-steps', $unselectedData + ['step' => 1])->render();

        $this->assertStringContainsString('name="action_type" value="NEW_ACCESS"', $stepOne);
        $this->assertStringContainsString('name="action_type" value="CHANGE_CONTACT"', $stepOne);
        $this->assertStringContainsString('data-emeeting-current-contact', $stepOne);
        $this->assertStringContainsString('radio-fieldset emeeting-service-type', $stepOne);
        $this->assertStringContainsString('class="emeeting-current-contact"', $stepOne);
        $this->assertStringContainsString('>ผู้ดูแลเดิม</h3>', $stepOne);
        $this->assertStringContainsString('name="current_contact_first"', $stepOne);
        $this->assertStringContainsString('name="current_contact_last"', $stepOne);
        $this->assertStringContainsString('data-emeeting-current-contact', $newAccessStepOne);
        $this->assertMatchesRegularExpression('/name="current_contact_first"[^>]*value=""[^>]*disabled/', $newAccessStepOne);
        $this->assertMatchesRegularExpression('/name="current_contact_last"[^>]*value=""[^>]*disabled/', $newAccessStepOne);
        $this->assertStringNotContainsString('ค่าที่ต้องล้าง', $newAccessStepOne);
        $this->assertStringContainsString('data-emeeting-current-contact', $unselectedStepOne);
        $this->assertMatchesRegularExpression('/name="current_contact_first"[^>]*disabled/', $unselectedStepOne);
        $this->assertMatchesRegularExpression('/name="current_contact_last"[^>]*disabled/', $unselectedStepOne);
        $this->assertStringNotContainsString('name="current_contact_first"', $stepTwo);
        $this->assertStringContainsString('name="contact_email"', $stepTwo);
        $this->assertStringContainsString('Framework Administrator', $stepTwo);
        $this->assertStringContainsString('name="dean_pers_id"', $stepThree);
        $this->assertStringContainsString('CC-ITED-05_000125.pdf', $stepThree);
    }

    public function test_cc_ited_05_pdf_contains_service_type_contacts_and_agreement(): void
    {
        $html = view('portal.emeeting-document-pdf', ['record' => [
            'form_type_code' => 'CC-ITED-05',
            'request_no' => 'SWU-2026-000125',
            'unit' => 'คณะวิทยาศาสตร์',
            'action_type' => 'CHANGE_CONTACT',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'contact_first' => 'ผู้ดูแล',
            'contact_last' => 'ใหม่',
            'contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'contact_email' => 'meeting@g.swu.ac.th',
            'contact_phone' => '17977',
            'submitted_at' => '2026-10-29',
            'officer_decision' => 'APPROVED',
            'officer_name' => 'เจ้าหน้าที่ทดสอบ',
            'officer_reviewed_at' => '30 ตุลาคม 2569',
            'execution_detail' => 'กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว',
        ]])->render();

        $this->assertStringContainsString('บริการขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงาน', $html);
        $this->assertStringContainsString('ผู้ดูแล เดิม', $html);
        $this->assertStringContainsString('ผู้ดูแล ใหม่', $html);
        $this->assertStringContainsString('ผู้ดูแลกรอบการประชุมของส่วนงาน (Framework Administrator)', $html);
        $this->assertStringContainsString('ชื่อ-นามสกุล', $html);
        $this->assertStringContainsString('<span>Email</span>', $html);
        $this->assertStringNotContainsString('ผู้ดูแลกรอบการประชุม ชื่อ-นามสกุล', $html);
        $this->assertStringContainsString('ข้อตกลง', $html);
        $this->assertStringContainsString('SWU e-Meeting', $html);
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่ดำเนินการ', $html);
        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertStringContainsString('กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว', $html);
        $this->assertStringNotContainsString('หมายเหตุ', $html);
    }

    public function test_cc_ited_05_dean_email_uses_the_styled_html_layout(): void
    {
        $html = view('emails.emeeting-dean-review', [
            'request' => (object) ['request_no' => 'SWU-2026-000125'],
            'detail' => (object) ['action_type' => 'CHANGE_CONTACT'],
            'currentContact' => (object) ['full_name' => 'ผู้ดูแล เดิม'],
            'contact' => (object) [
                'full_name' => 'ผู้ดูแล ใหม่',
                'position_name' => 'นักวิชาการคอมพิวเตอร์',
                'email' => 'meeting@g.swu.ac.th',
                'phone' => '17977',
            ],
            'actionLabel' => 'เปลี่ยนแปลงสิทธิ์ผู้ดูแล',
            'detailsUrl' => 'https://example.test/dean/reviews/125',
            'departmentName' => 'คณะวิทยาศาสตร์',
            'subdepartmentName' => 'ภาควิชาวิทยาการคอมพิวเตอร์',
            'expiresAtText' => '29 พฤศจิกายน 2569 เวลา 14:01 น.',
        ])->render();

        $this->assertStringContainsString('background:#123365', $html);
        $this->assertStringContainsString('มีคำขอสิทธิ์ผู้ดูแลกรอบการประชุมรอการรับรอง', $html);
        $this->assertStringContainsString('ผู้ดูแล เดิม', $html);
        $this->assertStringContainsString('ผู้ดูแล ใหม่', $html);
        $this->assertStringContainsString('meeting@g.swu.ac.th', $html);
        $this->assertStringContainsString('ดูรายละเอียดและพิจารณาคำร้อง', $html);
        $this->assertStringContainsString('https://example.test/dean/reviews/125', $html);
    }

    public function test_cc_ited_05_officer_can_record_and_edit_execution_detail_without_course_fields(): void
    {
        $record = [
            'id' => 5,
            'number' => 'SWU-2026-000125',
            'form_type_code' => 'CC-ITED-05',
            'project_name' => 'เปลี่ยนแปลงสิทธิ์ผู้ดูแลกรอบการประชุม SWU e-Meeting',
            'unit' => 'คณะวิทยาศาสตร์',
            'action_type' => 'CHANGE_CONTACT',
            'current_contact_first' => 'ผู้ดูแล',
            'current_contact_last' => 'เดิม',
            'contact_first' => 'ผู้ดูแล',
            'contact_last' => 'ใหม่',
            'contact_position' => 'นักวิชาการคอมพิวเตอร์',
            'contact_email' => 'meeting@g.swu.ac.th',
            'contact_phone' => '17977',
            'status' => 'UNDER_OFFICER_REVIEW',
            'document_name' => 'CC-ITED-05_000125.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_version_count' => 0,
        ];

        $requestData = view('components.request-data', compact('record'))->render();
        $officerDetail = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();

        $this->assertStringContainsString('ข้อมูลผู้ดูแลกรอบการประชุมเดิม', $requestData);
        $this->assertStringContainsString('ข้อมูลผู้ดูแลกรอบการประชุมที่จะได้รับสิทธิ์', $requestData);
        $this->assertStringNotContainsString('name="course_id"', $officerDetail);
        $this->assertStringContainsString('name="execution_detail"', $officerDetail);
        $this->assertStringContainsString('data-officer-execution-detail', $officerDetail);
        $this->assertStringContainsString('ข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม', $officerDetail);
        $this->assertStringContainsString('placeholder="ระบุรายละเอียดการดำเนินการที่เสร็จสิ้นแล้ว"', $officerDetail);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="execution_detail"[^>]*required/', $officerDetail);
        $this->assertStringContainsString('อนุมัติและบันทึกข้อมูลการดำเนินการ', $officerDetail);

        $record['status'] = 'COMPLETED';
        $record['execution_detail'] = 'กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว';
        $completedOfficerDetail = view('portal.detail', ['record' => $record, 'role' => 'officer'])->render();
        $requesterDetail = view('portal.request-detail', compact('record'))->render();

        $this->assertStringContainsString('กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว', $completedOfficerDetail);
        $this->assertStringContainsString('data-open-dialog="edit-result-dialog"', $completedOfficerDetail);
        $this->assertStringContainsString('action="'.route('officer.result.update', 5).'"', $completedOfficerDetail);
        $this->assertStringContainsString('name="notify_requester"', $completedOfficerDetail);
        $this->assertStringContainsString('ข้อมูลการเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม', $requesterDetail);
        $this->assertStringContainsString('กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว', $requesterDetail);
    }

    public function test_cc_ited_05_cannot_be_approved_without_execution_detail(): void
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
                'request_id' => 5,
                'form_type_code' => 'CC-ITED-05',
            ]);

            $response = $this->withSession(['portal.actor' => [
                'pers_id' => 4001,
                'dept_id' => 200,
                'buasri_id' => 'officer',
                'name' => 'เจ้าหน้าที่ทดสอบ',
                'email' => 'officer@g.swu.ac.th',
                'role' => 'officer',
            ]])->from('/officer/reviews/5')->post('/officer/reviews/5', [
                'decision' => 'pass',
            ]);

            $response
                ->assertRedirect('/officer/reviews/5')
                ->assertSessionHasErrors([
                    'execution_detail' => 'กรุณาระบุข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม',
                ]);
        } finally {
            DB::purge('course133');
        }
    }

    public function test_change_contact_requires_the_current_emeeting_administrator(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $directory = Mockery::mock(DepartmentDirectory::class);
        $directory->shouldReceive('options')->andReturn(collect([10 => 'คณะวิทยาศาสตร์']));
        $directory->shouldReceive('subdepartmentsFor')->with(10)->andReturn(collect());
        app()->instance(DepartmentDirectory::class, $directory);

        $response = $this->withSession([
            'portal.actor' => [
                'pers_id' => 100,
                'role' => 'user',
            ],
        ])->from('/request/emeeting/create/1')->post('/request/emeeting/create/1', [
            'target_dept_id' => 10,
            'action_type' => 'CHANGE_CONTACT',
            'navigation' => 'next',
        ]);

        $response->assertRedirect('/request/emeeting/create/1')
            ->assertSessionHasErrors(['current_contact_first', 'current_contact_last']);
    }
}
