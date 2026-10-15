<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.faculty_dean', N'U') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE [name] = N'IX_faculty_dean_active_department'
          AND [object_id] = OBJECT_ID(N'course133.faculty_dean')
    )
        DROP INDEX [IX_faculty_dean_active_department] ON [course133].[faculty_dean];

    IF EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = N'CK_faculty_dean_effective_period'
          AND [parent_object_id] = OBJECT_ID(N'course133.faculty_dean')
    )
        ALTER TABLE [course133].[faculty_dean] DROP CONSTRAINT [CK_faculty_dean_effective_period];

    IF COL_LENGTH(N'course133.faculty_dean', N'effective_from') IS NOT NULL
        ALTER TABLE [course133].[faculty_dean] DROP COLUMN [effective_from];

    IF COL_LENGTH(N'course133.faculty_dean', N'effective_to') IS NOT NULL
        ALTER TABLE [course133].[faculty_dean] DROP COLUMN [effective_to];

    ALTER TABLE [course133].[faculty_dean] ADD [is_active_code] char(1) NULL;

    EXEC(N'UPDATE [course133].[faculty_dean]
        SET [is_active_code] = CASE WHEN [is_active] = 1 THEN ''Y'' ELSE ''N'' END;');

    EXEC(N'ALTER TABLE [course133].[faculty_dean]
        ALTER COLUMN [is_active_code] char(1) NOT NULL;');

    IF EXISTS (
        SELECT 1 FROM sys.default_constraints
        WHERE [name] = N'DF_faculty_dean_is_active'
          AND [parent_object_id] = OBJECT_ID(N'course133.faculty_dean')
    )
        ALTER TABLE [course133].[faculty_dean] DROP CONSTRAINT [DF_faculty_dean_is_active];

    ALTER TABLE [course133].[faculty_dean] DROP COLUMN [is_active];
    EXEC sys.sp_rename N'course133.faculty_dean.is_active_code', N'is_active', N'COLUMN';

    EXEC(N'ALTER TABLE [course133].[faculty_dean]
        ADD CONSTRAINT [DF_faculty_dean_is_active] DEFAULT (''Y'') FOR [is_active];');

    EXEC(N'ALTER TABLE [course133].[faculty_dean]
        ADD CONSTRAINT [CK_faculty_dean_is_active] CHECK ([is_active] IN (''Y'', ''N''));');

    EXEC(N'CREATE INDEX [IX_faculty_dean_active_department]
        ON [course133].[faculty_dean] ([target_dept_id], [is_active]);');
END;
SQL);
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.faculty_dean', N'U') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE [name] = N'IX_faculty_dean_active_department'
          AND [object_id] = OBJECT_ID(N'course133.faculty_dean')
    )
        DROP INDEX [IX_faculty_dean_active_department] ON [course133].[faculty_dean];

    IF EXISTS (
        SELECT 1 FROM sys.check_constraints
        WHERE [name] = N'CK_faculty_dean_is_active'
          AND [parent_object_id] = OBJECT_ID(N'course133.faculty_dean')
    )
        ALTER TABLE [course133].[faculty_dean] DROP CONSTRAINT [CK_faculty_dean_is_active];

    ALTER TABLE [course133].[faculty_dean] ADD [is_active_bit] bit NULL;

    EXEC(N'UPDATE [course133].[faculty_dean]
        SET [is_active_bit] = CASE WHEN [is_active] = ''Y'' THEN 1 ELSE 0 END;');

    EXEC(N'ALTER TABLE [course133].[faculty_dean]
        ALTER COLUMN [is_active_bit] bit NOT NULL;');

    IF EXISTS (
        SELECT 1 FROM sys.default_constraints
        WHERE [name] = N'DF_faculty_dean_is_active'
          AND [parent_object_id] = OBJECT_ID(N'course133.faculty_dean')
    )
        ALTER TABLE [course133].[faculty_dean] DROP CONSTRAINT [DF_faculty_dean_is_active];

    ALTER TABLE [course133].[faculty_dean] DROP COLUMN [is_active];
    EXEC sys.sp_rename N'course133.faculty_dean.is_active_bit', N'is_active', N'COLUMN';

    EXEC(N'ALTER TABLE [course133].[faculty_dean]
        ADD CONSTRAINT [DF_faculty_dean_is_active] DEFAULT (1) FOR [is_active];');

    ALTER TABLE [course133].[faculty_dean] ADD [effective_from] date NULL;
    ALTER TABLE [course133].[faculty_dean] ADD [effective_to] date NULL;

    EXEC(N'ALTER TABLE [course133].[faculty_dean]
        ADD CONSTRAINT [CK_faculty_dean_effective_period]
        CHECK ([effective_to] IS NULL OR [effective_from] IS NULL OR [effective_to] >= [effective_from]);');

    EXEC(N'CREATE INDEX [IX_faculty_dean_active_department]
        ON [course133].[faculty_dean] ([target_dept_id], [is_active], [effective_from], [effective_to]);');
END;
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }
};
