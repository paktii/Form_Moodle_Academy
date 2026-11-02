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
    THROW 50103, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'[service_portal].[cc_ited_01_request_detail]', N'U') IS NULL
    THROW 50105, 'Table service_portal.cc_ited_01_request_detail does not exist.', 1;

IF EXISTS (
    SELECT 1
    FROM [service_portal].[cc_ited_01_request_detail]
    WHERE [domain_url] IS NULL
)
    THROW 50104, 'CC-ITED-01 domain_url must be populated before it can be changed to NOT NULL.', 1;

ALTER TABLE [service_portal].[cc_ited_01_request_detail]
    ALTER COLUMN [domain_url] varchar(1000) NOT NULL;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50103, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'[service_portal].[cc_ited_01_request_detail]', N'U') IS NULL
    THROW 50105, 'Table service_portal.cc_ited_01_request_detail does not exist.', 1;

ALTER TABLE [service_portal].[cc_ited_01_request_detail]
    ALTER COLUMN [domain_url] varchar(1000) NULL;
SQL);
    }
};
