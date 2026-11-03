<?php

namespace Tests\Unit;

use App\Services\ServicePortalRecipientResolver;
use App\Services\ServiceRequestWorkflowService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ServiceRequestWorkflowServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.course133', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('course133');
        $database = DB::connection('course133');
        $database->statement("ATTACH DATABASE ':memory:' AS service_portal");
        $database->unprepared(<<<'SQL'
CREATE TABLE service_portal.service_request (
    request_id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_no TEXT NOT NULL,
    form_type_code TEXT NOT NULL,
    requester_pers_id INTEGER NOT NULL,
    target_dept_id INTEGER NOT NULL,
    dean_pers_id INTEGER NOT NULL,
    request_title TEXT NOT NULL,
    status_code TEXT NOT NULL,
    submitted_at TEXT NULL,
    completed_at TEXT NULL,
    completed_by_pers_id INTEGER NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.cc_ited_06_request_detail (
    request_id INTEGER PRIMARY KEY,
    course_id TEXT NULL,
    execution_detail TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.cc_ited_01_request_detail (
    request_id INTEGER PRIMARY KEY,
    allocated_domain TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.cc_ited_04_request_detail (
    request_id INTEGER PRIMARY KEY,
    granted_space_gb NUMERIC NULL,
    execution_detail TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.cc_ited_05_request_detail (
    request_id INTEGER PRIMARY KEY,
    action_type TEXT NOT NULL,
    execution_detail TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
CREATE TABLE service_portal.dean_review (
    dean_review_id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    dean_pers_id INTEGER NOT NULL,
    decision TEXT NOT NULL,
    comment TEXT NULL,
    decided_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE service_portal.officer_review (
    officer_review_id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    officer_pers_id INTEGER NOT NULL,
    decision TEXT NOT NULL,
    rejection_reason TEXT NULL,
    reviewed_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE service_portal.request_status_history (
    history_id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    from_status_code TEXT NULL,
    to_status_code TEXT NOT NULL,
    changed_by_pers_id INTEGER NULL,
    change_source TEXT NOT NULL,
    comment TEXT NULL,
    changed_at TEXT DEFAULT CURRENT_TIMESTAMP
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
            'request_id' => 1,
            'request_no' => 'TEST-001',
            'form_type_code' => 'CC-ITED-06',
            'requester_pers_id' => 1001,
            'target_dept_id' => 2001,
            'dean_pers_id' => 3001,
            'request_title' => 'Test Moodle course',
            'status_code' => 'UNDER_OFFICER_REVIEW',
        ]);
        $database->table('service_portal.cc_ited_06_request_detail')->insert([
            'request_id' => 1,
            'course_id' => null,
            'execution_detail' => null,
        ]);
        $database->table('service_portal.service_request')->insert([
            'request_id' => 2,
            'request_no' => 'TEST-002',
            'form_type_code' => 'CC-ITED-01',
            'requester_pers_id' => 1001,
            'target_dept_id' => 2001,
            'dean_pers_id' => 3001,
            'request_title' => 'Test website',
            'status_code' => 'UNDER_OFFICER_REVIEW',
        ]);
        $database->table('service_portal.cc_ited_01_request_detail')->insert([
            'request_id' => 2,
            'allocated_domain' => null,
        ]);
        $database->table('service_portal.service_request')->insert([
            'request_id' => 3,
            'request_no' => 'TEST-003',
            'form_type_code' => 'CC-ITED-04',
            'requester_pers_id' => 1001,
            'target_dept_id' => 2001,
            'dean_pers_id' => 3001,
            'request_title' => 'Test website storage',
            'status_code' => 'UNDER_OFFICER_REVIEW',
        ]);
        $database->table('service_portal.cc_ited_04_request_detail')->insert([
            'request_id' => 3,
            'granted_space_gb' => null,
            'execution_detail' => null,
        ]);
        $database->table('service_portal.service_request')->insert([
            'request_id' => 4,
            'request_no' => 'TEST-004',
            'form_type_code' => 'CC-ITED-05',
            'requester_pers_id' => 1001,
            'target_dept_id' => 2001,
            'dean_pers_id' => 3001,
            'request_title' => 'Test e-Meeting administrator',
            'status_code' => 'UNDER_OFFICER_REVIEW',
        ]);
        $database->table('service_portal.cc_ited_05_request_detail')->insert([
            'request_id' => 4,
            'action_type' => 'NEW_ACCESS',
            'execution_detail' => null,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('course133');
        DB::purge('course133');

        parent::tearDown();
    }

    public function test_officer_approval_records_course_id_completion_audit_and_requester_notification_atomically(): void
    {
        $service = new ServiceRequestWorkflowService($this->recipientResolver());

        $request = $service->approveByOfficer(1, 4001, [
            'course_id' => 'COURSE-4832',
            'execution_detail' => 'สร้างรายวิชาและกำหนดค่าพื้นฐานเรียบร้อยแล้ว',
        ]);

        $this->assertSame('COMPLETED', $request->status_code);
        $this->assertSame(4001, $request->completed_by_pers_id);
        $this->assertSame('COURSE-4832', DB::connection('course133')
            ->table('service_portal.cc_ited_06_request_detail')
            ->where('request_id', 1)
            ->value('course_id'));
        $this->assertSame('สร้างรายวิชาและกำหนดค่าพื้นฐานเรียบร้อยแล้ว', DB::connection('course133')
            ->table('service_portal.cc_ited_06_request_detail')
            ->where('request_id', 1)
            ->value('execution_detail'));
        $this->assertDatabaseHas('service_portal.officer_review', [
            'request_id' => 1,
            'officer_pers_id' => 4001,
            'decision' => 'APPROVED',
        ], 'course133');
        $this->assertDatabaseHas('service_portal.request_status_history', [
            'request_id' => 1,
            'from_status_code' => 'UNDER_OFFICER_REVIEW',
            'to_status_code' => 'COMPLETED',
            'change_source' => 'OFFICER',
        ], 'course133');
        $this->assertDatabaseHas('service_portal.notification_outbox', [
            'request_id' => 1,
            'notification_type_code' => 'REQUEST_COMPLETED_REQUESTER',
            'recipient_email' => 'requester@g.swu.ac.th',
        ], 'course133');
        $this->assertDatabaseMissing('service_portal.notification_outbox', [
            'request_id' => 1,
            'notification_type_code' => 'REQUEST_COMPLETED_APPROVER',
        ], 'course133');
    }

    public function test_missing_course_id_rolls_back_the_entire_officer_approval(): void
    {
        $service = new ServiceRequestWorkflowService($this->recipientResolver());

        try {
            $service->approveByOfficer(1, 4001);
            $this->fail('Expected the workflow to reject a missing Course ID.');
        } catch (DomainException $exception) {
            $this->assertSame('course_id is required for CC-ITED-06.', $exception->getMessage());
        }

        $database = DB::connection('course133');
        $this->assertSame('UNDER_OFFICER_REVIEW', $database
            ->table('service_portal.service_request')
            ->where('request_id', 1)
            ->value('status_code'));
        $this->assertSame(0, $database->table('service_portal.officer_review')->count());
        $this->assertSame(0, $database->table('service_portal.request_status_history')->count());
        $this->assertSame(0, $database->table('service_portal.notification_outbox')->count());
    }

    public function test_course_execution_detail_is_optional_and_stored_as_null_when_omitted(): void
    {
        $service = new ServiceRequestWorkflowService($this->recipientResolver());

        $request = $service->approveByOfficer(1, 4001, ['course_id' => 'COURSE-4832']);

        $database = DB::connection('course133');
        $this->assertSame('COMPLETED', $request->status_code);
        $this->assertNull($database
            ->table('service_portal.cc_ited_06_request_detail')
            ->where('request_id', 1)
            ->value('execution_detail'));
        $this->assertSame(1, $database->table('service_portal.officer_review')->count());
        $this->assertSame(1, $database->table('service_portal.notification_outbox')->count());
    }

    public function test_website_officer_approval_records_allocated_domain_and_notifies_requester(): void
    {
        $service = new ServiceRequestWorkflowService($this->recipientResolver());

        $request = $service->approveByOfficer(2, 4001, ['allocated_domain' => 'training.swu.ac.th']);

        $this->assertSame('COMPLETED', $request->status_code);
        $this->assertSame('training.swu.ac.th', DB::connection('course133')
            ->table('service_portal.cc_ited_01_request_detail')
            ->where('request_id', 2)
            ->value('allocated_domain'));
        $this->assertDatabaseHas('service_portal.notification_outbox', [
            'request_id' => 2,
            'notification_type_code' => 'REQUEST_COMPLETED_REQUESTER',
            'recipient_email' => 'requester@g.swu.ac.th',
        ], 'course133');
        $this->assertDatabaseMissing('service_portal.notification_outbox', [
            'request_id' => 2,
            'notification_type_code' => 'REQUEST_COMPLETED_APPROVER',
        ], 'course133');
    }

    public function test_website_storage_approval_forces_one_gb_and_records_execution_detail(): void
    {
        $service = new ServiceRequestWorkflowService($this->recipientResolver());

        $request = $service->approveByOfficer(3, 4001, [
            'execution_detail' => 'เพิ่มพื้นที่เว็บไซต์เรียบร้อยแล้ว',
            'granted_space_gb' => 99,
        ]);

        $detail = DB::connection('course133')
            ->table('service_portal.cc_ited_04_request_detail')
            ->where('request_id', 3)
            ->first();

        $this->assertSame('COMPLETED', $request->status_code);
        $this->assertSame(1, (int) $detail->granted_space_gb);
        $this->assertSame('เพิ่มพื้นที่เว็บไซต์เรียบร้อยแล้ว', $detail->execution_detail);
    }

    public function test_emeeting_approval_records_execution_detail(): void
    {
        $service = new ServiceRequestWorkflowService($this->recipientResolver());

        $request = $service->approveByOfficer(4, 4001, [
            'execution_detail' => 'กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว',
        ]);

        $this->assertSame('COMPLETED', $request->status_code);
        $this->assertSame('กำหนดสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว', DB::connection('course133')
            ->table('service_portal.cc_ited_05_request_detail')
            ->where('request_id', 4)
            ->value('execution_detail'));
    }

    public function test_officer_rejection_ends_the_request_and_notifies_the_requester(): void
    {
        $resolver = Mockery::mock(ServicePortalRecipientResolver::class);
        $resolver->shouldReceive('requester')->once()->andReturn([
            'pers_id' => 1001,
            'email' => 'requester@g.swu.ac.th',
            'name' => 'Requester',
        ]);

        $request = (new ServiceRequestWorkflowService($resolver))
            ->rejectByOfficer(1, 4001, 'เอกสารไม่ถูกต้อง');

        $this->assertSame('REJECTED', $request->status_code);
        $this->assertDatabaseHas('service_portal.officer_review', [
            'request_id' => 1,
            'officer_pers_id' => 4001,
            'decision' => 'REJECTED',
            'rejection_reason' => 'เอกสารไม่ถูกต้อง',
        ], 'course133');
        $this->assertDatabaseHas('service_portal.request_status_history', [
            'request_id' => 1,
            'from_status_code' => 'UNDER_OFFICER_REVIEW',
            'to_status_code' => 'REJECTED',
            'change_source' => 'OFFICER',
        ], 'course133');
        $this->assertDatabaseHas('service_portal.notification_outbox', [
            'request_id' => 1,
            'notification_type_code' => 'REQUEST_REJECTED',
            'recipient_email' => 'requester@g.swu.ac.th',
        ], 'course133');
    }

    public function test_dean_approval_moves_the_request_directly_to_officer_review(): void
    {
        DB::connection('course133')
            ->table('service_portal.service_request')
            ->where('request_id', 1)
            ->update(['status_code' => 'PENDING_DEAN_REVIEW']);

        $resolver = Mockery::mock(ServicePortalRecipientResolver::class);
        $resolver->shouldReceive('roleRecipients')
            ->once()
            ->with('OFFICER')
            ->andReturn([[
                'pers_id' => 4001,
                'email' => 'officer@g.swu.ac.th',
                'name' => 'Officer',
            ]]);

        $request = (new ServiceRequestWorkflowService($resolver))->approveByDean(1, 3001);

        $this->assertSame('UNDER_OFFICER_REVIEW', $request->status_code);
        $this->assertDatabaseHas('service_portal.dean_review', [
            'request_id' => 1,
            'dean_pers_id' => 3001,
            'decision' => 'APPROVED',
        ], 'course133');
        $this->assertDatabaseHas('service_portal.request_status_history', [
            'request_id' => 1,
            'from_status_code' => 'PENDING_DEAN_REVIEW',
            'to_status_code' => 'UNDER_OFFICER_REVIEW',
            'change_source' => 'DEAN',
        ], 'course133');
        $this->assertDatabaseHas('service_portal.notification_outbox', [
            'request_id' => 1,
            'notification_type_code' => 'OFFICER_REVIEW_REQUIRED',
            'recipient_email' => 'officer@g.swu.ac.th',
        ], 'course133');
    }

    private function recipientResolver(): ServicePortalRecipientResolver
    {
        $resolver = Mockery::mock(ServicePortalRecipientResolver::class);
        $resolver->shouldReceive('requester')->once()->andReturn([
            'pers_id' => 1001,
            'email' => 'requester@g.swu.ac.th',
            'name' => 'Requester',
        ]);
        return $resolver;
    }
}
