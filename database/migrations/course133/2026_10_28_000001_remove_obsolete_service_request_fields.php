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
    THROW 50109, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY

UPDATE [service_portal].[service_request]
SET [status_code] = 'UNDER_OFFICER_REVIEW',
    [updated_at] = SYSDATETIME()
WHERE [status_code] = 'PENDING_SIGNED_DOCUMENT';

UPDATE [service_portal].[request_status_history]
SET [from_status_code] = 'UNDER_OFFICER_REVIEW'
WHERE [from_status_code] = 'PENDING_SIGNED_DOCUMENT';

UPDATE [service_portal].[request_status_history]
SET [to_status_code] = 'UNDER_OFFICER_REVIEW'
WHERE [to_status_code] = 'PENDING_SIGNED_DOCUMENT';

DELETE FROM [service_portal].[request_status]
WHERE [status_code] = 'PENDING_SIGNED_DOCUMENT';

IF OBJECT_ID(N'service_portal.CK_sp_request_version', N'C') IS NOT NULL
    ALTER TABLE [service_portal].[service_request] DROP CONSTRAINT [CK_sp_request_version];

IF OBJECT_ID(N'service_portal.DF_sp_request_version', N'D') IS NOT NULL
    ALTER TABLE [service_portal].[service_request] DROP CONSTRAINT [DF_sp_request_version];

IF COL_LENGTH(N'service_portal.service_request', N'form_version') IS NOT NULL
    ALTER TABLE [service_portal].[service_request] DROP COLUMN [form_version];

IF COL_LENGTH(N'service_portal.service_request', N'unsigned_pdf_downloaded_at') IS NOT NULL
    ALTER TABLE [service_portal].[service_request] DROP COLUMN [unsigned_pdf_downloaded_at];

IF COL_LENGTH(N'service_portal.service_request', N'approved_pdf_downloaded_at') IS NOT NULL
    ALTER TABLE [service_portal].[service_request] DROP COLUMN [approved_pdf_downloaded_at];

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
    THROW 50109, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY

IF NOT EXISTS (
    SELECT 1 FROM [service_portal].[request_status]
    WHERE [status_code] = 'PENDING_SIGNED_DOCUMENT'
)
    INSERT INTO [service_portal].[request_status]
        ([status_code], [status_name_th], [badge_style], [is_terminal], [sort_order])
    VALUES ('PENDING_SIGNED_DOCUMENT', N'รอเอกสารลงนาม', 'WARNING', 0, 30);

IF COL_LENGTH(N'service_portal.service_request', N'form_version') IS NULL
    ALTER TABLE [service_portal].[service_request]
        ADD [form_version] smallint NOT NULL
            CONSTRAINT [DF_sp_request_version] DEFAULT (1)
            CONSTRAINT [CK_sp_request_version] CHECK ([form_version] > 0);

IF COL_LENGTH(N'service_portal.service_request', N'unsigned_pdf_downloaded_at') IS NULL
    ALTER TABLE [service_portal].[service_request] ADD [unsigned_pdf_downloaded_at] datetime2 NULL;

IF COL_LENGTH(N'service_portal.service_request', N'approved_pdf_downloaded_at') IS NULL
    ALTER TABLE [service_portal].[service_request] ADD [approved_pdf_downloaded_at] datetime2 NULL;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }
};
