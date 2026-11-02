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
    THROW 50104, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    IF NOT EXISTS (
        SELECT 1 FROM [service_portal].[notification_type]
        WHERE [notification_type_code] = 'REQUEST_REJECTED'
    )
    BEGIN
        INSERT INTO [service_portal].[notification_type]
            ([notification_type_code], [notification_type_name_th], [template_key])
        VALUES
            ('REQUEST_REJECTED', N'แจ้งผู้ยื่นเมื่อเจ้าหน้าที่ไม่อนุมัติ', 'request-rejected');
    END;

    IF EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = N'CK_sp_officer_reason'
          AND [parent_object_id] = OBJECT_ID(N'service_portal.officer_review')
    )
        ALTER TABLE [service_portal].[officer_review] DROP CONSTRAINT [CK_sp_officer_reason];

    IF EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = N'CK_sp_officer_decision'
          AND [parent_object_id] = OBJECT_ID(N'service_portal.officer_review')
    )
        ALTER TABLE [service_portal].[officer_review] DROP CONSTRAINT [CK_sp_officer_decision];

    UPDATE [service_portal].[officer_review]
    SET [decision] = 'REJECTED'
    WHERE [decision] = 'RETURNED';

    IF COL_LENGTH(N'service_portal.officer_review', N'return_reason') IS NOT NULL
       AND COL_LENGTH(N'service_portal.officer_review', N'rejection_reason') IS NULL
        EXEC sp_rename N'service_portal.officer_review.return_reason', N'rejection_reason', 'COLUMN';

    IF NOT EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = N'CK_sp_officer_decision'
          AND [parent_object_id] = OBJECT_ID(N'service_portal.officer_review')
    )
        ALTER TABLE [service_portal].[officer_review]
            ADD CONSTRAINT [CK_sp_officer_decision]
            CHECK ([decision] IN ('APPROVED', 'REJECTED'));

    IF NOT EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = N'CK_sp_officer_reason'
          AND [parent_object_id] = OBJECT_ID(N'service_portal.officer_review')
    )
        EXEC(N'ALTER TABLE [service_portal].[officer_review]
            ADD CONSTRAINT [CK_sp_officer_reason] CHECK (
                ([decision] = ''APPROVED'' AND [rejection_reason] IS NULL)
                OR ([decision] = ''REJECTED'' AND NULLIF(LTRIM(RTRIM([rejection_reason])), N'''') IS NOT NULL)
            );');

    UPDATE [service_portal].[service_request]
    SET [status_code] = 'REJECTED', [updated_at] = SYSDATETIME()
    WHERE [status_code] = 'RETURNED_FOR_REVISION';

    UPDATE [service_portal].[request_status_history]
    SET [from_status_code] = 'REJECTED'
    WHERE [from_status_code] = 'RETURNED_FOR_REVISION';

    UPDATE [service_portal].[request_status_history]
    SET [to_status_code] = 'REJECTED'
    WHERE [to_status_code] = 'RETURNED_FOR_REVISION';

    UPDATE [service_portal].[notification_outbox]
    SET [notification_type_code] = 'REQUEST_REJECTED'
    WHERE [notification_type_code] = 'REQUEST_RETURNED';

    UPDATE [service_portal].[form_notification_recipient]
    SET [notification_type_code] = 'REQUEST_REJECTED'
    WHERE [notification_type_code] = 'REQUEST_RETURNED';

    DELETE FROM [service_portal].[notification_type]
    WHERE [notification_type_code] = 'REQUEST_RETURNED';

    DELETE FROM [service_portal].[request_status]
    WHERE [status_code] = 'RETURNED_FOR_REVISION';

    UPDATE [service_portal].[request_status]
    SET [status_name_th] = N'ไม่อนุมัติ',
        [badge_style] = 'DANGER',
        [is_terminal] = 1
    WHERE [status_code] = 'REJECTED';

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
        throw new RuntimeException('RETURNED_FOR_REVISION cannot be restored without changing rejected request history.');
    }
};
