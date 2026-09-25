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

BEGIN TRANSACTION;
BEGIN TRY
    IF COL_LENGTH(N'course133.course_request', N'activity_phase') IS NULL
        ALTER TABLE [course133].[course_request] ADD [activity_phase] nvarchar(250) NULL;

    EXEC(N'UPDATE [course133].[course_request]
    SET [activity_phase] = LTRIM(SUBSTRING([activity_round], CHARINDEX(N''เฟส'', [activity_round]), 250)),
        [activity_round] = RTRIM(LEFT([activity_round], CHARINDEX(N''เฟส'', [activity_round]) - 1))
    WHERE [learning_mode] = N''เปิดแบบตามวงรอบ (Phase/Batch-based)''
      AND [activity_phase] IS NULL
      AND CHARINDEX(N''เฟส'', [activity_round]) > 1');

    IF OBJECT_ID(N'course133.CK_request_activity_round', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_activity_round];
    IF OBJECT_ID(N'course133.CK_request_activity_details', N'C') IS NULL
        EXEC(N'ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_activity_details] CHECK (
            [learning_mode] <> N''เปิดแบบตามวงรอบ (Phase/Batch-based)''
            OR (NULLIF(LTRIM(RTRIM([activity_round])), N'''') IS NOT NULL
                AND NULLIF(LTRIM(RTRIM([activity_phase])), N'''') IS NOT NULL)
        )');

    IF OBJECT_ID(N'course133.officer_work_queue', N'V') IS NOT NULL
        EXEC sys.sp_refreshview N'course133.officer_work_queue';

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

BEGIN TRANSACTION;
BEGIN TRY
    DROP VIEW IF EXISTS [course133].[officer_work_queue];

    IF COL_LENGTH(N'course133.course_request', N'activity_phase') IS NOT NULL
        EXEC(N'UPDATE [course133].[course_request]
        SET [activity_round] = CONCAT([activity_round], N'' '', [activity_phase])
        WHERE [learning_mode] = N''เปิดแบบตามวงรอบ (Phase/Batch-based)''
          AND NULLIF(LTRIM(RTRIM([activity_phase])), N'''') IS NOT NULL');

    IF OBJECT_ID(N'course133.CK_request_activity_details', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_activity_details];
    IF COL_LENGTH(N'course133.course_request', N'activity_phase') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP COLUMN [activity_phase];
    IF OBJECT_ID(N'course133.CK_request_activity_round', N'C') IS NULL
        EXEC(N'ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_activity_round]
            CHECK ([learning_mode] <> N''เปิดแบบตามวงรอบ (Phase/Batch-based)'' OR NULLIF(LTRIM(RTRIM([activity_round])), N'''') IS NOT NULL)');

    EXEC(N'CREATE VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW'' AND EXISTS (
 SELECT 1 FROM [course133].[course_document] d WHERE d.[request_id] = r.[request_id]
 AND d.[document_type] = ''SIGNED_FORM'' AND d.[mime_type] = ''application/pdf'')');

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }
};
