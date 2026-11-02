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
    THROW 50107, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    DECLARE @approver_role_id smallint = (
        SELECT [role_id]
        FROM [service_portal].[app_role]
        WHERE [role_code] = 'APPROVER'
    );

    IF @approver_role_id IS NOT NULL
    BEGIN
        DELETE FROM [service_portal].[form_notification_recipient]
        WHERE [role_id] = @approver_role_id;

        DELETE FROM [service_portal].[user_role_assignment]
        WHERE [role_id] = @approver_role_id;

        DELETE FROM [service_portal].[app_role]
        WHERE [role_id] = @approver_role_id;
    END;

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
        throw new RuntimeException('APPROVER cannot be restored because the role and its assignments are retired.');
    }
};
