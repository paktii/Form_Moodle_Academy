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

IF OBJECT_ID(N'course133.faculty_dean', N'U') IS NULL
BEGIN
    CREATE TABLE [course133].[faculty_dean]
    (
        [faculty_dean_id] bigint IDENTITY PRIMARY KEY,
        [target_dept_id] int NOT NULL,
        [dean_pers_id] int NOT NULL,
        [is_active] bit NOT NULL CONSTRAINT [DF_faculty_dean_is_active] DEFAULT (1),
        [effective_from] date NULL,
        [effective_to] date NULL,
        [created_at] datetime2 NOT NULL CONSTRAINT [DF_faculty_dean_created_at] DEFAULT (SYSDATETIME()),
        [updated_at] datetime2 NOT NULL CONSTRAINT [DF_faculty_dean_updated_at] DEFAULT (SYSDATETIME()),
        CONSTRAINT [UQ_faculty_dean_department_person] UNIQUE ([target_dept_id], [dean_pers_id]),
        CONSTRAINT [CK_faculty_dean_effective_period] CHECK ([effective_to] IS NULL OR [effective_from] IS NULL OR [effective_to] >= [effective_from])
    );

    CREATE INDEX [IX_faculty_dean_active_department]
        ON [course133].[faculty_dean] ([target_dept_id], [is_active], [effective_from], [effective_to]);
END;

IF COL_LENGTH(N'course133.course_request', N'dean_pers_id') IS NULL
    ALTER TABLE [course133].[course_request] ADD [dean_pers_id] int NULL;

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE [name] = N'CK_request_status'
      AND [parent_object_id] = OBJECT_ID(N'course133.course_request')
)
    ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_status];

ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_status]
    CHECK ([status] IN ('PENDING_SIGNED_DOCUMENT','PENDING_DEAN_REVIEW','UNDER_OFFICER_REVIEW','RETURNED_FOR_REVISION','PENDING_APPROVAL','REJECTED','PENDING_COURSE_ID','COURSE_ID_RECORDED'));

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE [name] = N'IX_request_dean_status'
      AND [object_id] = OBJECT_ID(N'course133.course_request')
)
    CREATE INDEX [IX_request_dean_status]
        ON [course133].[course_request] ([dean_pers_id], [status], [created_at] DESC);
SQL);
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.course_request', N'U') IS NOT NULL
BEGIN
    UPDATE [course133].[course_request]
    SET [status] = 'PENDING_SIGNED_DOCUMENT'
    WHERE [status] = 'PENDING_DEAN_REVIEW';

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE [name] = N'IX_request_dean_status'
          AND [object_id] = OBJECT_ID(N'course133.course_request')
    )
        DROP INDEX [IX_request_dean_status] ON [course133].[course_request];

    IF EXISTS (
        SELECT 1
        FROM sys.check_constraints
        WHERE [name] = N'CK_request_status'
          AND [parent_object_id] = OBJECT_ID(N'course133.course_request')
    )
        ALTER TABLE [course133].[course_request] DROP CONSTRAINT [CK_request_status];

    ALTER TABLE [course133].[course_request] ADD CONSTRAINT [CK_request_status]
        CHECK ([status] IN ('PENDING_SIGNED_DOCUMENT','UNDER_OFFICER_REVIEW','RETURNED_FOR_REVISION','PENDING_APPROVAL','REJECTED','PENDING_COURSE_ID','COURSE_ID_RECORDED'));

    IF COL_LENGTH(N'course133.course_request', N'dean_pers_id') IS NOT NULL
        ALTER TABLE [course133].[course_request] DROP COLUMN [dean_pers_id];
END;

DROP TABLE IF EXISTS [course133].[faculty_dean];
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }
};
