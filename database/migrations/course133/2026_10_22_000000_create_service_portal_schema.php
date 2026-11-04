<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Keep the service portal migration on the existing Server 133 connection. */
    protected $connection = 'course133';

    public function up(): void
    {
        $sql = file_get_contents(__DIR__.'/create_service_portal_schema.sql');

        if ($sql === false) {
            throw new RuntimeException('The service portal SQL Server schema file could not be read.');
        }

        $this->database()->unprepared($sql);
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50101, 'This migration may run only on academy_db1447.', 1;

DROP TABLE IF EXISTS [service_portal].[course_instructor];
DROP TABLE IF EXISTS [service_portal].[moodle_course_request_detail];
DROP TABLE IF EXISTS [service_portal].[emeeting_request_detail];
DROP TABLE IF EXISTS [service_portal].[website_request_detail];
DROP TABLE IF EXISTS [service_portal].[notification_outbox];
DROP TABLE IF EXISTS [service_portal].[request_status_history];
DROP TABLE IF EXISTS [service_portal].[officer_review];
DROP TABLE IF EXISTS [service_portal].[dean_review];
DROP TABLE IF EXISTS [service_portal].[request_document];
DROP TABLE IF EXISTS [service_portal].[request_contact];
DROP TABLE IF EXISTS [service_portal].[service_request];
DROP TABLE IF EXISTS [service_portal].[form_officer_assignment];
DROP TABLE IF EXISTS [service_portal].[form_head_assignment];
DROP TABLE IF EXISTS [service_portal].[form_notification_recipient];
DROP TABLE IF EXISTS [service_portal].[faculty_dean];
DROP TABLE IF EXISTS [service_portal].[user_role_assignment];
DROP TABLE IF EXISTS [service_portal].[course_category];
DROP TABLE IF EXISTS [service_portal].[project_type];
DROP TABLE IF EXISTS [service_portal].[app_role];
DROP TABLE IF EXISTS [service_portal].[website_platform];
DROP TABLE IF EXISTS [service_portal].[notification_type];
DROP TABLE IF EXISTS [service_portal].[document_type];
DROP TABLE IF EXISTS [service_portal].[contact_role];
DROP TABLE IF EXISTS [service_portal].[request_status];
DROP TABLE IF EXISTS [service_portal].[form_type];

IF SCHEMA_ID(N'service_portal') IS NOT NULL
    EXEC(N'DROP SCHEMA [service_portal]');
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }
};
