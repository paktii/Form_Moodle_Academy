<?php

namespace Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class WebsiteFormViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag());
    }

    public function test_all_cc_ited_01_form_steps_render_with_the_expected_workflow_fields(): void
    {
        $baseData = [
            'formName' => 'แบบฟอร์มขอใช้พื้นที่สำหรับจัดทำเว็บไซต์',
            'steps' => config('course-workflow.website_steps'),
            'platforms' => collect(['PHP_MYSQL' => 'PHP + MySQL', 'DNN' => 'DNN (DotNetNuke)']),
            'departments' => collect([10 => 'คณะวิทยาศาสตร์']),
            'subdepartments' => collect([20 => 'ภาควิชาวิทยาการคอมพิวเตอร์']),
            'subdepartmentParents' => [20 => 10],
            'deanOptions' => [30 => 'ผู้รับรองตัวอย่าง', 713796 => 'นางสาว ภัคธีมา รัตนคช (pakteema@g.swu.ac.th)'],
            'data' => [
                'request_no' => 'SR-2026-000123',
                'contact_first' => 'ผู้ยื่น',
                'contact_last' => 'คำร้อง',
                'contact_position' => 'นักวิชาการคอมพิวเตอร์',
                'contact_email' => 'requester@g.swu.ac.th',
                'contact_phone' => '15044',
            ],
        ];

        $stepOne = view('portal.website-form-steps', $baseData + ['step' => 1])->render();
        $stepTwo = view('portal.website-form-steps', $baseData + ['step' => 2])->render();
        $stepThree = view('portal.website-form-steps', $baseData + ['step' => 3])->render();

        $this->assertStringContainsString('<span>แบบฟอร์มขอใช้พื้นที่สำหรับจัดทำเว็บไซต์</span>', $stepOne);
        $this->assertStringContainsString('requester-page-header--active-context', $stepOne);
        $this->assertStringContainsString('<span class="requester-page-header__context">สร้างคำร้อง</span>', $stepOne);
        $this->assertStringContainsString('<span class="requester-page-header__context">สร้างคำร้อง</span>', $stepTwo);
        $this->assertStringContainsString('<span class="requester-page-header__context">สร้างคำร้อง</span>', $stepThree);
        $this->assertStringContainsString('name="website_name"', $stepOne);
        $this->assertMatchesRegularExpression('/name="domain_url"[^>]*required/', $stepOne);
        $this->assertStringNotContainsString('โดเมน (URL) ที่ต้องการ (ถ้ามี)', $stepOne);
        $this->assertStringContainsString('name="platform_code"', $stepOne);
        $this->assertStringNotContainsString('อื่น ๆ (ระบุ)', $stepOne);
        $this->assertStringNotContainsString('name="platform_other"', $stepOne);
        $this->assertStringContainsString('name="contact_email"', $stepTwo);
        $this->assertMatchesRegularExpression('/name="contact_first"[^>]*value="ผู้ยื่น"/', $stepTwo);
        $this->assertDoesNotMatchRegularExpression('/name="contact_(?:first|email)"[^>]*(?:readonly|disabled)/', $stepTwo);
        $this->assertStringNotContainsString('name="accepted_terms"', $stepTwo);
        $this->assertStringNotContainsString('ข้าพเจ้าอ่านและยอมรับคำรับรองข้างต้น', $stepTwo);
        $this->assertStringContainsString('ข้าพเจ้าขอรับรองว่าจะปฏิบัติตามกฏระเบียบ', $stepTwo);
        $this->assertStringContainsString('ไม่ใช้บริการนี้เพื่อผลประโยชน์ทางการค้าทั้งเพื่อตนเอง หรือเพื่อบุคคลอื่นใดทั้งสิ้น', $stepTwo);
        $this->assertStringContainsString('ข้าพเจ้าจะรับผิดชอบในการนำแฟ้มข้อมูลต่างๆ ไปเก็บบน Web Server', $stepTwo);
        $this->assertStringContainsString('name="dean_pers_id"', $stepThree);
        $this->assertStringContainsString('data-searchable-select-show-all-on-focus', $stepThree);
        $this->assertStringContainsString('pakteema@g.swu.ac.th', $stepThree);
        $this->assertStringContainsString('CC-ITED-01_000123.pdf', $stepThree);
    }
}
