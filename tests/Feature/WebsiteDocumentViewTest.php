<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebsiteDocumentViewTest extends TestCase
{
    public function test_cc_ited_01_pdf_view_contains_website_fields_and_single_workflow_result(): void
    {
        $html = view('portal.website-document-pdf', [
            'record' => [
                'form_type_code' => 'CC-ITED-01',
                'request_no' => 'SR-2026-000123',
                'unit' => 'คณะวิทยาศาสตร์',
                'website_name' => 'เว็บไซต์โครงการตัวอย่าง',
                'site_scope_type' => 'PROJECT',
                'site_scope_label' => 'เว็บไซต์โครงการ',
                'service_starts_on' => '2026-11-01',
                'service_ends_on' => '2027-10-31',
                'domain_url' => 'sample.swu.ac.th',
                'platform_code' => 'PHP_MYSQL',
                'platform_label' => 'PHP + MySQL',
                'contact_first' => 'สมชาย',
                'contact_last' => 'ใจดี',
                'contact_position' => 'นักวิชาการคอมพิวเตอร์',
                'contact_email' => 'somchai@g.swu.ac.th',
                'contact_phone' => '15044',
                'submitted_at' => '2026-10-27',
                'officer_decision' => 'REJECTED',
                'officer_name' => 'เจ้าหน้าที่ทดสอบ',
                'officer_reviewed_at' => '28 ตุลาคม 2569',
                'reason' => 'ข้อมูลโดเมนไม่ถูกต้อง',
                'allocated_domain' => 'allocated.swu.ac.th',
            ],
        ])->render();

        $this->assertStringContainsString('บริการขอใช้พื้นที่สำหรับจัดทำเว็บไซต์', $html);
        $this->assertStringContainsString('เว็บไซต์โครงการตัวอย่าง', $html);
        $this->assertStringContainsString('PHP + MySQL', $html);
        $this->assertStringContainsString('ชื่อผู้ดูแลเว็บไซต์', $html);
        $this->assertStringContainsString('E-mail', $html);
        $this->assertStringContainsString('โทรศัพท์ติดต่อ / ภายใน', $html);
        $this->assertStringContainsString('website-field website-field--multiline', $html);
        $this->assertStringContainsString('allocated.swu.ac.th', $html);
        $this->assertStringNotContainsString('ผู้รับแจ้งเพื่อทราบ', $html);
        $this->assertStringContainsString('วันที่รับเอกสาร', $html);
        $this->assertStringContainsString('วันที่พิจารณา', $html);
        $this->assertStringContainsString('ไม่อนุมัติ', $html);
        $this->assertStringContainsString('เหตุผล', $html);
        $this->assertStringContainsString('ข้อมูลโดเมนไม่ถูกต้อง', $html);
        $this->assertStringContainsString('วันที่ดำเนินการ', $html);
        $this->assertMatchesRegularExpression('/<b>ผลการดำเนินการ<\/b><span>allocated\.swu\.ac\.th<\/span>/', $html);
        $this->assertMatchesRegularExpression('/document-checkbox is-checked[^>]*><\/span> ไม่อนุมัติ/', $html);
        $this->assertSame(2, substr_count($html, 'เจ้าหน้าที่ทดสอบ'));
        $this->assertSame(2, substr_count($html, '28 ตุลาคม 2569'));
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่รับเอกสาร', $html);
        $this->assertStringContainsString('สำหรับผู้พิจารณา', $html);
        $this->assertStringContainsString('สำหรับเจ้าหน้าที่ดำเนินการ', $html);
        $this->assertStringContainsString('ผลการดำเนินการ', $html);
        $this->assertStringContainsString('website-staff__domain--allocation', $html);
        $this->assertStringContainsString('overflow-wrap: anywhere', $html);
        $this->assertStringContainsString('max-width: 210mm', $html);
        $this->assertStringContainsString('align-items: start', $html);
        $this->assertStringNotContainsString('website-staff__allocation-lines', $html);
        $this->assertStringNotContainsString('Course ID', $html);
    }
}
