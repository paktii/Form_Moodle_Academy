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

IF OBJECT_ID(N'course133.course_document', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_document', N'roster_acknowledged_at') IS NULL
    ALTER TABLE [course133].[course_document]
        ADD [roster_acknowledged_at] datetime2 NULL;

IF OBJECT_ID(N'course133.course_document', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_document', N'roster_acknowledged_by_pers_id') IS NULL
    ALTER TABLE [course133].[course_document]
        ADD [roster_acknowledged_by_pers_id] int NULL;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_document', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_document', N'roster_acknowledged_by_pers_id') IS NOT NULL
    ALTER TABLE [course133].[course_document]
        DROP COLUMN [roster_acknowledged_by_pers_id];

IF OBJECT_ID(N'course133.course_document', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.course_document', N'roster_acknowledged_at') IS NOT NULL
    ALTER TABLE [course133].[course_document]
        DROP COLUMN [roster_acknowledged_at];
SQL);
    }
};
