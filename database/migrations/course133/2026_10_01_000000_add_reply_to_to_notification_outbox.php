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

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'reply_to_email') IS NULL
    ALTER TABLE [course133].[notification_outbox]
        ADD [reply_to_email] varchar(254) NULL;

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'reply_to_name') IS NULL
    ALTER TABLE [course133].[notification_outbox]
        ADD [reply_to_name] nvarchar(300) NULL;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'reply_to_name') IS NOT NULL
    ALTER TABLE [course133].[notification_outbox]
        DROP COLUMN [reply_to_name];

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'reply_to_email') IS NOT NULL
    ALTER TABLE [course133].[notification_outbox]
        DROP COLUMN [reply_to_email];
SQL);
    }
};
