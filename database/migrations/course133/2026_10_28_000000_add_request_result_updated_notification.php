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
    THROW 50113, 'This migration may run only on academy_db1447.', 1;

IF NOT EXISTS (
    SELECT 1 FROM [service_portal].[notification_type]
    WHERE [notification_type_code] = 'REQUEST_RESULT_UPDATED'
)
BEGIN
    INSERT INTO [service_portal].[notification_type]
        ([notification_type_code], [notification_type_name_th], [template_key])
    VALUES
        ('REQUEST_RESULT_UPDATED', N'แจ้งผู้ยื่นเมื่อเจ้าหน้าที่แก้ไขผลการดำเนินการ', 'request-result-updated');
END;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50113, 'This migration may run only on academy_db1447.', 1;

IF EXISTS (
    SELECT 1 FROM [service_portal].[notification_outbox]
    WHERE [notification_type_code] = 'REQUEST_RESULT_UPDATED'
)
    THROW 50114, 'REQUEST_RESULT_UPDATED is referenced by notification_outbox and cannot be removed.', 1;

DELETE FROM [service_portal].[notification_type]
WHERE [notification_type_code] = 'REQUEST_RESULT_UPDATED';
SQL);
    }
};
