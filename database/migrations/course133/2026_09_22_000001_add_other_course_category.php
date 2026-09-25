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

IF OBJECT_ID(N'course133.course_category', N'U') IS NOT NULL
BEGIN
    IF EXISTS (SELECT 1 FROM [course133].[course_category] WHERE [category_code] = 'OTHER')
        UPDATE [course133].[course_category]
        SET [category_name_th] = N'อื่น ๆ (ระบุ)', [is_active] = 1
        WHERE [category_code] = 'OTHER';
    ELSE
        INSERT INTO [course133].[course_category] ([category_code], [category_name_th], [is_active])
        VALUES ('OTHER', N'อื่น ๆ (ระบุ)', 1);
END

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
BEGIN
    IF COL_LENGTH(N'course133.course_request', N'category_other') IS NULL
        ALTER TABLE [course133].[course_request] ADD [category_other] nvarchar(500) NULL;

    IF OBJECT_ID(N'course133.CK_request_category_other', N'C') IS NULL
        EXEC(N'ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_category_other]
            CHECK ([category_code] <> ''OTHER'' OR NULLIF(LTRIM(RTRIM([category_other])), N'''') IS NOT NULL)');
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

    IF OBJECT_ID(N'course133.CK_request_category_other', N'C') IS NOT NULL
        EXEC(N'ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_category_other]');

    IF COL_LENGTH(N'course133.course_request', N'category_other') IS NOT NULL
        EXEC(N'ALTER TABLE [course133].[course_request] DROP COLUMN [category_other]');
END

IF OBJECT_ID(N'course133.course_category', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM [course133].[course_request] WHERE [category_code] = 'OTHER')
    DELETE FROM [course133].[course_category] WHERE [category_code] = 'OTHER';

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
