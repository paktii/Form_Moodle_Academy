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

IF OBJECT_ID(N'service_portal.cc_ited_04_request_detail', N'U') IS NULL
    THROW 50114, 'Table service_portal.cc_ited_04_request_detail does not exist.', 1;

IF COL_LENGTH(N'service_portal.cc_ited_04_request_detail', N'execution_detail') IS NULL
    ALTER TABLE [service_portal].[cc_ited_04_request_detail]
        ADD [execution_detail] nvarchar(max) NULL;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50113, 'This migration may run only on academy_db1447.', 1;

IF COL_LENGTH(N'service_portal.cc_ited_04_request_detail', N'execution_detail') IS NOT NULL
    ALTER TABLE [service_portal].[cc_ited_04_request_detail]
        DROP COLUMN [execution_detail];
SQL);
    }
};
