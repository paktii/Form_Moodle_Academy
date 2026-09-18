<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        foreach ($this->roles() as $actorKey => $roleCode) {
            $roleId = $this->database()->table('course133.app_role')
                ->where('role_code', $roleCode)
                ->value('role_id');

            if ($roleId === null) {
                throw new RuntimeException("Missing course133.app_role row: $roleCode");
            }

            $this->database()->table('course133.user_role_assignment')->updateOrInsert(
                [
                    'pers_id' => (int) config("course-workflow.actors.$actorKey.pers_id"),
                    'role_id' => $roleId,
                ],
                ['is_active' => true],
            );
        }
    }

    public function down(): void
    {
        foreach ($this->roles() as $actorKey => $roleCode) {
            $roleId = $this->database()->table('course133.app_role')
                ->where('role_code', $roleCode)
                ->value('role_id');

            if ($roleId !== null) {
                $this->database()->table('course133.user_role_assignment')
                    ->where('pers_id', (int) config("course-workflow.actors.$actorKey.pers_id"))
                    ->where('role_id', $roleId)
                    ->delete();
            }
        }
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }

    private function roles(): array
    {
        return [
            'user' => 'REQUESTER',
            'officer' => 'OFFICER',
            'approver' => 'APPROVER',
        ];
    }
};
