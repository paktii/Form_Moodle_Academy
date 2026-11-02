<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF OBJECT_ID(N'course133.officer_work_queue', N'V') IS NOT NULL
    DROP VIEW [course133].[officer_work_queue];

IF SCHEMA_ID(N'course133') IS NOT NULL
    EXEC(N'DROP SCHEMA [course133]');
SQL);
    }

    public function down(): void
    {
        // The legacy view depended on disposable tables that were intentionally removed.
    }
};
