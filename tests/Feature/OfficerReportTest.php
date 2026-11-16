<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class OfficerReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag());
    }

    public function test_officer_list_has_report_button(): void
    {
        $records = collect();
        $role = 'officer';
        $statuses = [];

        $html = view('portal.requests', compact('records', 'role', 'statuses'))->render();

        $this->assertStringContainsString('href="'.route('officer.reports').'"', $html);
        $this->assertStringContainsString('chart-pie.svg', $html);
        $this->assertStringContainsString('ดูรายงาน', $html);
        $this->assertStringNotContainsString('data-value="NOT_ENDORSED"', $html);
        $this->assertStringNotContainsString('>ไม่รับรอง<', $html);
    }

    public function test_not_endorsed_status_has_its_own_label(): void
    {
        $html = Blade::render('<x-status-badge status="NOT_ENDORSED" />');

        $this->assertStringContainsString('ไม่รับรอง', $html);
        $this->assertStringNotContainsString('>ไม่อนุมัติ<', $html);
    }

    public function test_report_counts_requests_by_year_month_and_form(): void
    {
        config()->set('database.connections.course133', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('course133');

        try {
            $database = DB::connection('course133');
            $database->statement("ATTACH DATABASE ':memory:' AS service_portal");
            $database->unprepared(<<<'SQL'
CREATE TABLE service_portal.form_type (
    form_type_code TEXT PRIMARY KEY,
    short_name_th TEXT NOT NULL,
    sort_order INTEGER NOT NULL
);
CREATE TABLE service_portal.service_request (
    request_id INTEGER PRIMARY KEY,
    form_type_code TEXT NOT NULL,
    status_code TEXT NOT NULL,
    submitted_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.officer_review (
    officer_review_id INTEGER PRIMARY KEY,
    request_id INTEGER NOT NULL,
    decision TEXT NOT NULL
);
CREATE TABLE service_portal.dean_review (
    dean_review_id INTEGER PRIMARY KEY,
    request_id INTEGER NOT NULL,
    decision TEXT NOT NULL
);
SQL);
            $database->table('service_portal.form_type')->insert([
                ['form_type_code' => 'CC-ITED-01', 'short_name_th' => 'ขอใช้พื้นที่จัดทำเว็บไซต์', 'sort_order' => 10],
                ['form_type_code' => 'CC-ITED-04', 'short_name_th' => 'ขอเพิ่มพื้นที่เว็บไซต์', 'sort_order' => 20],
            ]);
            $database->table('service_portal.service_request')->insert([
                ['request_id' => 1, 'form_type_code' => 'CC-ITED-01', 'status_code' => 'COMPLETED', 'submitted_at' => '2026-11-05 09:00:00'],
                ['request_id' => 2, 'form_type_code' => 'CC-ITED-01', 'status_code' => 'COMPLETED', 'submitted_at' => '2026-11-10 10:00:00'],
                ['request_id' => 3, 'form_type_code' => 'CC-ITED-04', 'status_code' => 'COMPLETED', 'submitted_at' => '2026-10-01 11:00:00'],
                ['request_id' => 4, 'form_type_code' => 'CC-ITED-04', 'status_code' => 'COMPLETED', 'submitted_at' => '2025-11-02 12:00:00'],
                ['request_id' => 5, 'form_type_code' => 'CC-ITED-01', 'status_code' => 'UNDER_OFFICER_REVIEW', 'submitted_at' => '2026-11-12 13:00:00'],
                ['request_id' => 6, 'form_type_code' => 'CC-ITED-04', 'status_code' => 'REJECTED', 'submitted_at' => '2026-11-15 14:00:00'],
                ['request_id' => 7, 'form_type_code' => 'CC-ITED-01', 'status_code' => 'NOT_ENDORSED', 'submitted_at' => '2026-11-18 15:00:00'],
            ]);
            $database->table('service_portal.officer_review')->insert([
                'officer_review_id' => 1,
                'request_id' => 6,
                'decision' => 'REJECTED',
            ]);
            $database->table('service_portal.dean_review')->insert([
                'dean_review_id' => 1,
                'request_id' => 7,
                'decision' => 'NOT_ENDORSED',
            ]);

            $response = $this->withSession(['portal.actor' => [
                'pers_id' => 100,
                'dept_id' => 200,
                'buasri_id' => 'officer',
                'name' => 'เจ้าหน้าที่ทดสอบ',
                'email' => 'officer@g.swu.ac.th',
                'label' => 'เจ้าหน้าที่',
                'role' => 'officer',
            ]])->get('/officer/reports?year=2026&month=11');

            $response->assertOk()
                ->assertViewHas('yearTotal', 3)
                ->assertViewHas('monthTotal', 2)
                ->assertViewHas('monthShare', 66.7)
                ->assertViewHas('selectedYearDisplay', 2569)
                ->assertSee('คำร้องแบบฟอร์มขอรับบริการ')
                ->assertSee('รายงานสถิติคำร้อง')
                ->assertSee('requester-page-header__home-logo', false)
                ->assertSee('requester-page-header__home-title', false)
                ->assertSee('href="'.route('officer.reviews').'"', false)
                ->assertSee('แสดงคำร้องที่อนุมัติแล้ว')
                ->assertSee('data-report-filter', false)
                ->assertDontSee('แสดงรายงาน')
                ->assertSee('href="'.route('officer.reports').'"', false)
                ->assertSee('ล้างตัวกรอง')
                ->assertSee('filter-x.svg')
                ->assertSee('สัดส่วนคำร้องที่อนุมัติแล้วแยกตามแบบฟอร์ม')
                ->assertSee('สัดส่วนเดือนพฤศจิกายนเทียบกับทั้งปี')
                ->assertSee('66.7%')
                ->assertSee('report-donut', false)
                ->assertSee('pathLength="100"', false)
                ->assertSee('stroke-linecap="butt"', false)
                ->assertSee('พฤศจิกายน 2569');
            $response->assertViewHas('reportRows', function ($rows): bool {
                $website = $rows->firstWhere('code', 'CC-ITED-01');
                $storage = $rows->firstWhere('code', 'CC-ITED-04');

                return $website['year_count'] === 2
                    && $website['month_count'] === 2
                    && $storage['year_count'] === 1
                    && $storage['month_count'] === 0;
            });

            $filteredResponse = $this->withSession(['portal.actor' => [
                'pers_id' => 100,
                'dept_id' => 200,
                'buasri_id' => 'officer',
                'name' => 'เจ้าหน้าที่ทดสอบ',
                'email' => 'officer@g.swu.ac.th',
                'label' => 'เจ้าหน้าที่',
                'role' => 'officer',
            ]])->get('/officer/reports?year=2026&month=11&form_type=CC-ITED-04');

            $filteredResponse->assertOk()
                ->assertViewHas('selectedFormType', 'CC-ITED-04')
                ->assertViewHas('yearTotal', 1)
                ->assertViewHas('monthTotal', 0)
                ->assertViewHas('reportRows', fn ($rows): bool => $rows->count() === 1
                    && $rows->first()['code'] === 'CC-ITED-04');

            $inProgressResponse = $this->withSession(['portal.actor' => [
                'pers_id' => 100,
                'role' => 'officer',
            ]])->get('/officer/reports?year=2026&month=11&decision=in_progress');
            $inProgressResponse->assertOk()
                ->assertViewHas('selectedDecision', 'in_progress')
                ->assertViewHas('yearTotal', 1)
                ->assertViewHas('monthTotal', 1)
                ->assertSee('คำร้องที่กำลังดำเนินการโดยเจ้าหน้าที่');

            $officerRejectedResponse = $this->withSession(['portal.actor' => [
                'pers_id' => 100,
                'role' => 'officer',
            ]])->get('/officer/reports?year=2026&month=11&decision=officer_rejected');
            $officerRejectedResponse->assertOk()
                ->assertViewHas('selectedDecision', 'officer_rejected')
                ->assertViewHas('yearTotal', 1)
                ->assertViewHas('monthTotal', 1)
                ->assertSee('คำร้องที่เจ้าหน้าที่ไม่อนุมัติ');

            $deanRejectedResponse = $this->withSession(['portal.actor' => [
                'pers_id' => 100,
                'role' => 'officer',
            ]])->get('/officer/reports?year=2026&month=11&decision=dean_rejected');
            $deanRejectedResponse->assertOk()
                ->assertViewHas('selectedDecision', 'dean_rejected')
                ->assertViewHas('yearTotal', 1)
                ->assertViewHas('monthTotal', 1)
                ->assertSee('คำร้องที่ผู้รับรองของส่วนงานไม่รับรอง');

            $allDecisionsResponse = $this->withSession(['portal.actor' => [
                'pers_id' => 100,
                'role' => 'officer',
            ]])->get('/officer/reports?year=2026&month=11&decision=all');
            $allDecisionsResponse->assertOk()
                ->assertViewHas('selectedDecision', 'all')
                ->assertViewHas('yearTotal', 5)
                ->assertViewHas('monthTotal', 4)
                ->assertSee('ผลการพิจารณาที่สิ้นสุดแล้วทั้งหมด')
                ->assertSee('สัดส่วนผลการพิจารณาที่สิ้นสุดแล้ว')
                ->assertSee('ไม่อนุมัติโดยเจ้าหน้าที่')
                ->assertSee('ไม่รับรองโดยผู้รับรองของส่วนงาน')
                ->assertViewHas('decisionBreakdownRows', function ($rows): bool {
                    $approved = $rows->firstWhere('key', 'approved');
                    $officerRejected = $rows->firstWhere('key', 'officer_rejected');
                    $deanRejected = $rows->firstWhere('key', 'dean_rejected');

                    return $approved['year_count'] === 3
                        && $approved['month_count'] === 2
                        && $officerRejected['year_count'] === 1
                        && $officerRejected['month_count'] === 1
                        && $deanRejected['year_count'] === 1
                        && $deanRejected['month_count'] === 1;
                });
        } finally {
            DB::purge('course133');
        }
    }
}
