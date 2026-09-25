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
BEGIN
    IF COL_LENGTH(N'course133.course_request', N'activity_round') IS NULL
        ALTER TABLE [course133].[course_request] ADD [activity_round] nvarchar(500) NULL;

    IF OBJECT_ID(N'course133.CK_request_learning_period', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_learning_period];

    EXEC(N'ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_learning_period] CHECK (
        ([learning_mode] = N''แบบเรียนรู้ตามอัธยาศัยตลอดเวลา'' AND [starts_on] IS NULL AND [ends_on] IS NULL)
        OR ([learning_mode] = N''แบบกำหนดช่วงเวลาเรียน'' AND [starts_on] IS NOT NULL AND [ends_on] IS NOT NULL AND [ends_on] >= [starts_on])
        OR ([learning_mode] IN (N''เปิดแบบตามวงรอบ (Phase/Batch-based)'', N''แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)'') AND [starts_on] IS NOT NULL AND [ends_on] IS NOT NULL AND [ends_on] >= [starts_on])
    )');

    IF OBJECT_ID(N'course133.CK_request_activity_round', N'C') IS NULL
        EXEC(N'ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_activity_round]
            CHECK ([learning_mode] <> N''เปิดแบบตามวงรอบ (Phase/Batch-based)'' OR NULLIF(LTRIM(RTRIM([activity_round])), N'''') IS NOT NULL)');
END

IF OBJECT_ID(N'course133.officer_work_queue', N'V') IS NOT NULL
    EXEC sys.sp_refreshview N'course133.officer_work_queue';
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
BEGIN
    DROP VIEW IF EXISTS [course133].[officer_work_queue];

    IF OBJECT_ID(N'course133.CK_request_activity_round', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_activity_round];

    IF OBJECT_ID(N'course133.CK_request_learning_period', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_learning_period];

    IF COL_LENGTH(N'course133.course_request', N'activity_round') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP COLUMN [activity_round];

    EXEC(N'ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_learning_period] CHECK (
        ([learning_mode] = N''แบบเรียนรู้ตามอัธยาศัยตลอดเวลา'' AND [starts_on] IS NULL AND [ends_on] IS NULL)
        OR ([learning_mode] = N''แบบกำหนดช่วงเวลาเรียน'' AND [starts_on] IS NOT NULL AND [ends_on] IS NOT NULL AND [ends_on] >= [starts_on])
    )');

    EXEC(N'CREATE VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW'' AND EXISTS (
 SELECT 1 FROM [course133].[course_document] d WHERE d.[request_id] = r.[request_id]
 AND d.[document_type] = ''SIGNED_FORM'' AND d.[mime_type] = ''application/pdf'')');
END
SQL);
    }
};
