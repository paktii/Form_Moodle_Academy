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
    THROW 50106, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    UPDATE [service_portal].[service_request]
    SET [status_code] = 'UNDER_OFFICER_REVIEW',
        [completed_at] = NULL,
        [completed_by_pers_id] = NULL,
        [updated_at] = SYSDATETIME()
    WHERE [status_code] = 'PENDING_COURSE_ID';

    UPDATE [service_portal].[request_status_history]
    SET [from_status_code] = 'UNDER_OFFICER_REVIEW'
    WHERE [from_status_code] = 'PENDING_COURSE_ID';

    UPDATE [service_portal].[request_status_history]
    SET [to_status_code] = 'UNDER_OFFICER_REVIEW'
    WHERE [to_status_code] = 'PENDING_COURSE_ID';

    DELETE FROM [service_portal].[request_status]
    WHERE [status_code] = 'PENDING_COURSE_ID';

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
        throw new RuntimeException('PENDING_COURSE_ID cannot be restored without recreating the retired workflow.');
    }
};
