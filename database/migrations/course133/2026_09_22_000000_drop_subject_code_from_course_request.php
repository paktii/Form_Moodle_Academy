<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_request', N'subject_code') IS NOT NULL
BEGIN
    DROP VIEW IF EXISTS [course133].[officer_work_queue];
    ALTER TABLE [course133].[course_request] DROP COLUMN [subject_code];
END

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
   AND OBJECT_ID(N'course133.officer_work_queue', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW'' AND EXISTS (
 SELECT 1 FROM [course133].[course_document] d WHERE d.[request_id] = r.[request_id]
 AND d.[document_type] = ''SIGNED_FORM'' AND d.[mime_type] = ''application/pdf'')');
END
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_request', N'subject_code') IS NULL
BEGIN
    DROP VIEW IF EXISTS [course133].[officer_work_queue];
    ALTER TABLE [course133].[course_request] ADD [subject_code] varchar(100) NULL;
END

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
   AND OBJECT_ID(N'course133.officer_work_queue', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW'' AND EXISTS (
 SELECT 1 FROM [course133].[course_document] d WHERE d.[request_id] = r.[request_id]
 AND d.[document_type] = ''SIGNED_FORM'' AND d.[mime_type] = ''application/pdf'')');
END
SQL);
    }
};
