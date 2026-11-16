<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        if (strtolower((string) $database->getDatabaseName()) !== 'academy_db1447') {
            throw new RuntimeException('This migration may run only on academy_db1447.');
        }

        $database->table('service_portal.app_role')->updateOrInsert(
            ['role_code' => 'ADMIN'],
            ['role_name_th' => 'ผู้ดูแลระบบ', 'is_active' => 'Y'],
        );

        if (app()->environment('local', 'uat')) {
            $roleId = $database->table('service_portal.app_role')->where('role_code', 'ADMIN')->value('role_id');
            foreach ([713796, 701341] as $personId) {
                $database->table('service_portal.user_role_assignment')->updateOrInsert(
                    ['pers_id' => $personId, 'role_id' => $roleId],
                    ['is_active' => 'Y', 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $database = DB::connection($this->connection);
        $roleId = $database->table('service_portal.app_role')->where('role_code', 'ADMIN')->value('role_id');

        if ($roleId !== null) {
            $database->table('service_portal.user_role_assignment')->where('role_id', $roleId)->delete();
            $database->table('service_portal.app_role')->where('role_id', $roleId)->delete();
        }
    }
};
