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

IF OBJECT_ID(N'course133.dean_review', N'U') IS NULL
BEGIN
    CREATE TABLE [course133].[dean_review]
    (
        [dean_review_id] bigint IDENTITY PRIMARY KEY,
        [request_id] bigint NOT NULL,
        [dean_pers_id] int NOT NULL,
        [decision] varchar(20) NOT NULL,
        [comment] nvarchar(max) NULL,
        [decided_at] datetime2 NOT NULL CONSTRAINT [DF_dean_review_decided_at] DEFAULT (SYSDATETIME()),
        CONSTRAINT [CK_dean_review_decision] CHECK ([decision] IN ('APPROVED','REJECTED')),
        CONSTRAINT [CK_dean_review_reason] CHECK ([decision] = 'APPROVED' OR NULLIF(LTRIM(RTRIM([comment])), N'') IS NOT NULL),
        CONSTRAINT [FK_dean_review_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id])
    );
END;
SQL);
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

DROP TABLE IF EXISTS [course133].[dean_review];
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }
};
