<?php

namespace Tests\Feature;

use App\Services\PersonnelDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery\MockInterface;
use Tests\TestCase;

class RequesterDeanEmailResendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::share('errors', new ViewErrorBag());
    }

    public function test_request_detail_shows_resend_button_only_while_waiting_for_dean(): void
    {
        $record = $this->requestRecord('PENDING_DEAN_REVIEW');
        $html = view('portal.request-detail', compact('record'))->render();

        $this->assertStringContainsString('ส่งอีเมลถึงคณบดีอีกครั้ง', $html);
        $this->assertStringContainsString('action="'.route('requests.dean-email.resend', 41).'"', $html);
        $this->assertStringContainsString('id="resend-dean-email-dialog"', $html);

        $record = $this->requestRecord('COMPLETED');
        $html = view('portal.request-detail', compact('record'))->render();

        $this->assertStringNotContainsString('ส่งอีเมลถึงคณบดีอีกครั้ง', $html);
        $this->assertStringNotContainsString('id="resend-dean-email-dialog"', $html);
    }

    public function test_resend_queues_one_dean_email_and_does_not_duplicate_a_pending_email(): void
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
CREATE TABLE service_portal.service_request (
    request_id INTEGER PRIMARY KEY,
    request_no TEXT NOT NULL,
    form_type_code TEXT NOT NULL,
    requester_pers_id INTEGER NOT NULL,
    target_dept_id INTEGER NOT NULL,
    dean_pers_id INTEGER NOT NULL,
    request_title TEXT NOT NULL,
    status_code TEXT NOT NULL,
    submitted_at TEXT NULL,
    completed_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.notification_outbox (
    notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    notification_type_code TEXT NOT NULL,
    recipient_pers_id INTEGER NULL,
    recipient_email TEXT NOT NULL,
    recipient_name TEXT NULL,
    reply_to_email TEXT NULL,
    reply_to_name TEXT NULL,
    delivery_status TEXT NOT NULL,
    attempt_count INTEGER NOT NULL,
    next_attempt_at TEXT NULL,
    claimed_at TEXT NULL,
    sent_at TEXT NULL,
    last_error TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
SQL);
            $database->table('service_portal.service_request')->insert([
                'request_id' => 41,
                'request_no' => 'SWU-2026-RESEND41',
                'form_type_code' => 'CC-ITED-04',
                'requester_pers_id' => 1001,
                'target_dept_id' => 10,
                'dean_pers_id' => 2001,
                'request_title' => 'คำร้องทดสอบส่งอีเมลซ้ำ',
                'status_code' => 'PENDING_DEAN_REVIEW',
            ]);

            $dean = (object) ['person_id' => 2001, 'gafe_account' => 'dean@g.swu.ac.th'];
            $this->mock(PersonnelDirectory::class, function (MockInterface $mock) use ($dean): void {
                $mock->shouldReceive('findByPersonId')->with(2001)->andReturn($dean);
                $mock->shouldReceive('email')->with($dean)->andReturn('dean@g.swu.ac.th');
            });

            $session = ['portal.actor' => [
                'pers_id' => 1001,
                'name' => 'ผู้ยื่นคำร้องทดสอบ',
                'email' => 'requester@g.swu.ac.th',
                'role' => 'user',
            ]];

            $firstResponse = $this->withoutMiddleware()->withSession($session)
                ->post(route('requests.dean-email.resend', 41));

            $firstResponse->assertRedirect(route('requests.show', 41));
            $this->assertSame(1, $database->table('service_portal.notification_outbox')->count());
            $this->assertDatabaseHas('service_portal.notification_outbox', [
                'request_id' => 41,
                'notification_type_code' => 'DEAN_REVIEW_REQUIRED',
                'recipient_pers_id' => 2001,
                'recipient_email' => 'dean@g.swu.ac.th',
                'delivery_status' => 'PENDING',
            ], 'course133');

            $secondResponse = $this->withoutMiddleware()->withSession($session)
                ->post(route('requests.dean-email.resend', 41));

            $secondResponse->assertRedirect(route('requests.show', 41));
            $secondResponse->assertSessionHas('success', 'อีเมลแจ้งคณบดีอยู่ในคิวส่งแล้ว<br>ระบบจะไม่สร้างรายการส่งซ้ำ');
            $this->assertSame(1, $database->table('service_portal.notification_outbox')->count());
        } finally {
            DB::purge('course133');
        }
    }

    private function requestRecord(string $status): array
    {
        return [
            'id' => 41,
            'number' => 'SWU-2026-RESEND41',
            'form_type_code' => 'CC-ITED-04',
            'project_name' => 'คำร้องทดสอบส่งอีเมลซ้ำ',
            'unit' => 'คณะวิทยาศาสตร์',
            'website_name' => 'เว็บไซต์ตัวอย่าง',
            'domain_url' => 'example.swu.ac.th',
            'requested_space_gb' => '1.00',
            'status' => $status,
            'document_name' => 'CC-ITED-04_RESEND41.pdf',
            'additional_files' => [],
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_version_count' => 0,
        ];
    }
}
