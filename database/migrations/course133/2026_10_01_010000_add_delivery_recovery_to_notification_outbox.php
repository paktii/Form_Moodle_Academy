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
   AND COL_LENGTH(N'course133.notification_outbox', N'processing_started_at') IS NULL
    ALTER TABLE [course133].[notification_outbox]
        ADD [processing_started_at] datetime2 NULL;

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'next_attempt_at') IS NULL
    ALTER TABLE [course133].[notification_outbox]
        ADD [next_attempt_at] datetime2 NULL;

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE [name] = N'IX_notification_delivery_ready'
         AND [object_id] = OBJECT_ID(N'course133.notification_outbox')
   )
    CREATE INDEX [IX_notification_delivery_ready]
        ON [course133].[notification_outbox]
        ([delivery_status], [next_attempt_at], [processing_started_at], [created_at])
        WHERE [delivery_status] IN ('PENDING', 'FAILED', 'SENDING');
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE [name] = N'IX_notification_delivery_ready'
         AND [object_id] = OBJECT_ID(N'course133.notification_outbox')
   )
    DROP INDEX [IX_notification_delivery_ready] ON [course133].[notification_outbox];

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'next_attempt_at') IS NOT NULL
    ALTER TABLE [course133].[notification_outbox]
        DROP COLUMN [next_attempt_at];

IF OBJECT_ID(N'course133.notification_outbox', N'U') IS NOT NULL
   AND COL_LENGTH(N'course133.notification_outbox', N'processing_started_at') IS NOT NULL
    ALTER TABLE [course133].[notification_outbox]
        DROP COLUMN [processing_started_at];
SQL);
    }
};
