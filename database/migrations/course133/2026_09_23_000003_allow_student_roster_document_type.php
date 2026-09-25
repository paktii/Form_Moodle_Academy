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
    IF OBJECT_ID(N'course133.CK_document_type', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_document] DROP CONSTRAINT [CK_document_type];

    ALTER TABLE [course133].[course_document] ADD CONSTRAINT [CK_document_type]
        CHECK ([document_type] IN ('GENERATED_FORM', 'SIGNED_FORM', 'ADDITIONAL_DOCUMENT', 'STUDENT_ROSTER'));

    ;WITH first_additional AS (
        SELECT d.[document_id], ROW_NUMBER() OVER (PARTITION BY d.[request_id] ORDER BY d.[uploaded_at], d.[document_id]) AS row_number
        FROM [course133].[course_document] d
        INNER JOIN [course133].[course_request] r ON r.[request_id] = d.[request_id]
        WHERE d.[document_type] = 'ADDITIONAL_DOCUMENT'
          AND r.[enrollment_method] = N'ผู้ดูแลระบบนำเข้ารายชื่อ'
          AND NOT EXISTS (
              SELECT 1 FROM [course133].[course_document] roster
              WHERE roster.[request_id] = d.[request_id]
                AND roster.[document_type] = 'STUDENT_ROSTER'
          )
    )
    UPDATE d SET [document_type] = 'STUDENT_ROSTER'
    FROM [course133].[course_document] d
    INNER JOIN first_additional candidate ON candidate.[document_id] = d.[document_id]
    WHERE candidate.[row_number] = 1;

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
    UPDATE [course133].[course_document]
    SET [document_type] = 'ADDITIONAL_DOCUMENT'
    WHERE [document_type] = 'STUDENT_ROSTER';

    IF OBJECT_ID(N'course133.CK_document_type', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_document] DROP CONSTRAINT [CK_document_type];

    ALTER TABLE [course133].[course_document] ADD CONSTRAINT [CK_document_type]
        CHECK ([document_type] IN ('GENERATED_FORM', 'SIGNED_FORM', 'ADDITIONAL_DOCUMENT'));

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }
};
