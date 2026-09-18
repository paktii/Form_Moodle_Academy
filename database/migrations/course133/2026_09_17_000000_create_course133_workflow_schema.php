<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Keep the 133 migration isolated from Laravel's default database. */
    protected $connection = 'course133';

    public function up(): void
    {
        $sql = file_get_contents(__DIR__ . '/create_course133_workflow.sql');

        if ($sql === false) {
            throw new RuntimeException('The SQL Server 133 schema file could not be read.');
        }

        // IDs sourced from Server 199 are plain int columns for now. No cross-server FKs are created.
        $this->database()->unprepared($sql);
        $this->seedConfiguredRoleAssignments();
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

DROP VIEW IF EXISTS [course133].[officer_work_queue];
DROP TABLE IF EXISTS [course133].[notification_outbox];
DROP TABLE IF EXISTS [course133].[request_status_history];
DROP TABLE IF EXISTS [course133].[course_approval];
DROP TABLE IF EXISTS [course133].[officer_review];
DROP TABLE IF EXISTS [course133].[course_document];
DROP TABLE IF EXISTS [course133].[course_instructor];
DROP TABLE IF EXISTS [course133].[course_request];
DROP TABLE IF EXISTS [course133].[user_role_assignment];
DROP TABLE IF EXISTS [course133].[course_category];
DROP TABLE IF EXISTS [course133].[project_type];
DROP TABLE IF EXISTS [course133].[app_role];
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }

    private function seedConfiguredRoleAssignments(): void
    {
        foreach (
            [
                'user' => 'REQUESTER',
                'officer' => 'OFFICER',
                'approver' => 'APPROVER',
            ] as $actorKey => $roleCode
        ) {
            $roleId = $this->database()->table('course133.app_role')
                ->where('role_code', $roleCode)
                ->value('role_id');

            $this->database()->table('course133.user_role_assignment')->updateOrInsert(
                [
                    'pers_id' => (int) config("course-workflow.actors.$actorKey.pers_id"),
                    'role_id' => $roleId,
                ],
                ['is_active' => true],
            );
        }
    }
};
