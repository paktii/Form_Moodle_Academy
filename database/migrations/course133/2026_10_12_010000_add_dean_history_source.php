<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE [name] = N'CK_history_source'
      AND [parent_object_id] = OBJECT_ID(N'course133.request_status_history')
)
    ALTER TABLE [course133].[request_status_history] DROP CONSTRAINT [CK_history_source];

ALTER TABLE [course133].[request_status_history] ADD CONSTRAINT [CK_history_source]
    CHECK ([change_source] IN ('REQUESTER','DEAN','OFFICER','APPROVER','SYSTEM'));

EXEC(N'CREATE OR ALTER VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW''');
SQL);
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

UPDATE [course133].[request_status_history]
SET [change_source] = 'SYSTEM'
WHERE [change_source] = 'DEAN';

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE [name] = N'CK_history_source'
      AND [parent_object_id] = OBJECT_ID(N'course133.request_status_history')
)
    ALTER TABLE [course133].[request_status_history] DROP CONSTRAINT [CK_history_source];

ALTER TABLE [course133].[request_status_history] ADD CONSTRAINT [CK_history_source]
    CHECK ([change_source] IN ('REQUESTER','OFFICER','APPROVER','SYSTEM'));

EXEC(N'CREATE OR ALTER VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW'' AND EXISTS (
 SELECT 1 FROM [course133].[course_document] d WHERE d.[request_id] = r.[request_id]
 AND d.[document_type] = ''SIGNED_FORM'' AND d.[mime_type] = ''application/pdf'')');
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }
};
