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
    THROW 50108, 'This migration may run only on academy_db1447.', 1;

IF COL_LENGTH(N'service_portal.cc_ited_01_request_detail', N'allocated_domain') IS NULL
    THROW 50109, 'Column service_portal.cc_ited_01_request_detail.allocated_domain does not exist.', 1;

ALTER TABLE [service_portal].[cc_ited_01_request_detail]
    ALTER COLUMN [allocated_domain] nvarchar(max) NULL;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50108, 'This migration may run only on academy_db1447.', 1;

IF EXISTS (
    SELECT 1
    FROM [service_portal].[cc_ited_01_request_detail]
    WHERE LEN([allocated_domain]) > 1000
)
    THROW 50110, 'allocated_domain contains values longer than 1000 characters and cannot be reduced.', 1;

ALTER TABLE [service_portal].[cc_ited_01_request_detail]
    ALTER COLUMN [allocated_domain] varchar(1000) NULL;
SQL);
    }
};
