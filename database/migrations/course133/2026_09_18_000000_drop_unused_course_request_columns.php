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
BEGIN
    IF OBJECT_ID(N'course133.CK_request_year', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_year];

    IF OBJECT_ID(N'course133.CK_request_hours', N'C') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_hours];

    IF COL_LENGTH(N'course133.course_request', N'semester') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP COLUMN [semester];

    IF COL_LENGTH(N'course133.course_request', N'academic_year') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP COLUMN [academic_year];

    IF COL_LENGTH(N'course133.course_request', N'learning_hours') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP COLUMN [learning_hours];
END
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
BEGIN
    IF COL_LENGTH(N'course133.course_request', N'semester') IS NULL
        ALTER TABLE [course133].[course_request] ADD [semester] varchar(20) NULL;

    IF COL_LENGTH(N'course133.course_request', N'academic_year') IS NULL
        ALTER TABLE [course133].[course_request] ADD [academic_year] smallint NULL;

    IF COL_LENGTH(N'course133.course_request', N'learning_hours') IS NULL
    BEGIN
        ALTER TABLE [course133].[course_request] ADD [learning_hours] decimal(8,2) NULL;
        UPDATE [course133].[course_request] SET [learning_hours] = 1 WHERE [learning_hours] IS NULL;
        ALTER TABLE [course133].[course_request] ALTER COLUMN [learning_hours] decimal(8,2) NOT NULL;
    END

    IF OBJECT_ID(N'course133.CK_request_year', N'C') IS NULL
        ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_year]
            CHECK ([academic_year] IS NULL OR [academic_year] BETWEEN 2500 AND 2700);

    IF OBJECT_ID(N'course133.CK_request_hours', N'C') IS NULL
        ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_hours]
            CHECK ([learning_hours] > 0);
END
SQL);
    }
};
