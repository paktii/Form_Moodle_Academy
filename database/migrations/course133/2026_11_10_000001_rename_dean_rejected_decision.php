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
    THROW 50116, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    IF EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = 'CK_sp_dean_comment'
          AND [parent_object_id] = OBJECT_ID(N'[service_portal].[dean_review]')
    )
        ALTER TABLE [service_portal].[dean_review] DROP CONSTRAINT [CK_sp_dean_comment];

    IF EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = 'CK_sp_dean_decision'
          AND [parent_object_id] = OBJECT_ID(N'[service_portal].[dean_review]')
    )
        ALTER TABLE [service_portal].[dean_review] DROP CONSTRAINT [CK_sp_dean_decision];

    UPDATE [service_portal].[dean_review]
    SET [decision] = 'NOT_ENDORSED'
    WHERE [decision] = 'REJECTED';

    ALTER TABLE [service_portal].[dean_review]
        ADD CONSTRAINT [CK_sp_dean_decision]
        CHECK ([decision] IN ('APPROVED', 'NOT_ENDORSED'));

    ALTER TABLE [service_portal].[dean_review]
        ADD CONSTRAINT [CK_sp_dean_comment]
        CHECK ([decision] = 'APPROVED' OR NULLIF(LTRIM(RTRIM([comment])), N'') IS NOT NULL);

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
    THROW 50116, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    ALTER TABLE [service_portal].[dean_review] DROP CONSTRAINT [CK_sp_dean_comment];
    ALTER TABLE [service_portal].[dean_review] DROP CONSTRAINT [CK_sp_dean_decision];

    UPDATE [service_portal].[dean_review]
    SET [decision] = 'REJECTED'
    WHERE [decision] = 'NOT_ENDORSED';

    ALTER TABLE [service_portal].[dean_review]
        ADD CONSTRAINT [CK_sp_dean_decision]
        CHECK ([decision] IN ('APPROVED', 'REJECTED'));

    ALTER TABLE [service_portal].[dean_review]
        ADD CONSTRAINT [CK_sp_dean_comment]
        CHECK ([decision] = 'APPROVED' OR NULLIF(LTRIM(RTRIM([comment])), N'') IS NOT NULL);

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }
};
