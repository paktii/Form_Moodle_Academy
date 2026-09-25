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
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_request', N'approved_pdf_downloaded_at') IS NULL
    ALTER TABLE [course133].[course_request]
        ADD [approved_pdf_downloaded_at] datetime2 NULL;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_request', N'approved_pdf_downloaded_at') IS NOT NULL
    ALTER TABLE [course133].[course_request]
        DROP COLUMN [approved_pdf_downloaded_at];
SQL);
    }
};
