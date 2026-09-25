<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared($this->migrationSql(7, 'SYSDATETIME()'));
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared($this->migrationSql(-7, 'SYSUTCDATETIME()'));
    }

    private function migrationSql(int $hourOffset, string $defaultExpression): string
    {
        $timestampColumns = [
            ['course_request', 'submitted_at'],
            ['course_request', 'created_at'],
            ['course_request', 'updated_at'],
            ['course_document', 'uploaded_at'],
            ['officer_review', 'reviewed_at'],
            ['course_approval', 'decided_at'],
            ['request_status_history', 'changed_at'],
            ['notification_outbox', 'sent_at'],
            ['notification_outbox', 'created_at'],
        ];
        $defaultColumns = [
            ['course_request', 'created_at'],
            ['course_request', 'updated_at'],
            ['course_document', 'uploaded_at'],
            ['officer_review', 'reviewed_at'],
            ['course_approval', 'decided_at'],
            ['request_status_history', 'changed_at'],
            ['notification_outbox', 'created_at'],
        ];

        $updates = collect($timestampColumns)->map(fn (array $column) => sprintf(
            'UPDATE [course133].[%s] SET [%s] = DATEADD(HOUR, %d, [%s]) WHERE [%s] IS NOT NULL;',
            $column[0],
            $column[1],
            $hourOffset,
            $column[1],
            $column[1],
        ))->implode("\n");

        $defaults = collect($defaultColumns)->map(function (array $column) use ($defaultExpression): string {
            [$table, $field] = $column;
            $constraint = "DF_{$table}_{$field}";

            return <<<SQL
DECLARE @{$table}_{$field}_default sysname;
SELECT @{$table}_{$field}_default = dc.name
FROM sys.default_constraints dc
JOIN sys.columns c ON c.default_object_id = dc.object_id
WHERE dc.parent_object_id = OBJECT_ID(N'course133.{$table}') AND c.name = N'{$field}';
IF @{$table}_{$field}_default IS NOT NULL
    EXEC(N'ALTER TABLE [course133].[{$table}] DROP CONSTRAINT [' + @{$table}_{$field}_default + N']');
ALTER TABLE [course133].[{$table}] ADD CONSTRAINT [{$constraint}] DEFAULT ({$defaultExpression}) FOR [{$field}];
SQL;
        })->implode("\n");

        return <<<SQL
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

BEGIN TRANSACTION;
BEGIN TRY
{$updates}
{$defaults}
    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL;
    }
};
