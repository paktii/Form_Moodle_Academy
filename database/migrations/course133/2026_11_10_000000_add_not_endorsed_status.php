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
    THROW 50115, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    IF NOT EXISTS (
        SELECT 1 FROM [service_portal].[request_status]
        WHERE [status_code] = 'NOT_ENDORSED'
    )
    BEGIN
        INSERT INTO [service_portal].[request_status]
            ([status_code], [status_name_th], [badge_style], [is_terminal], [sort_order])
        VALUES
            ('NOT_ENDORSED', N'ไม่รับรอง', 'DANGER', 1, 50);
    END;

    UPDATE request_row
    SET request_row.[status_code] = 'NOT_ENDORSED',
        request_row.[updated_at] = SYSDATETIME()
    FROM [service_portal].[service_request] AS request_row
    WHERE request_row.[status_code] = 'REJECTED'
      AND EXISTS (
          SELECT 1
          FROM [service_portal].[dean_review] AS dean_review
          WHERE dean_review.[request_id] = request_row.[request_id]
            AND dean_review.[decision] = 'REJECTED'
      )
      AND NOT EXISTS (
          SELECT 1
          FROM [service_portal].[officer_review] AS officer_review
          WHERE officer_review.[request_id] = request_row.[request_id]
            AND officer_review.[decision] = 'REJECTED'
      );

    UPDATE history_row
    SET history_row.[to_status_code] = 'NOT_ENDORSED'
    FROM [service_portal].[request_status_history] AS history_row
    WHERE history_row.[to_status_code] = 'REJECTED'
      AND history_row.[change_source] = 'DEAN';

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
    THROW 50115, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    UPDATE [service_portal].[service_request]
    SET [status_code] = 'REJECTED',
        [updated_at] = SYSDATETIME()
    WHERE [status_code] = 'NOT_ENDORSED';

    UPDATE [service_portal].[request_status_history]
    SET [from_status_code] = 'REJECTED'
    WHERE [from_status_code] = 'NOT_ENDORSED';

    UPDATE [service_portal].[request_status_history]
    SET [to_status_code] = 'REJECTED'
    WHERE [to_status_code] = 'NOT_ENDORSED';

    DELETE FROM [service_portal].[request_status]
    WHERE [status_code] = 'NOT_ENDORSED';

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }
};
