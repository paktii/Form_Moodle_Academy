<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PortalIdentityService
{
    public function __construct(
        private readonly LdapAuthenticator $ldapAuthenticator,
        private readonly PersonnelDirectory $personnelDirectory,
    ) {}

    public function authenticate(string $buasriId, string $password): ?array
    {
        if (! $this->ldapAuthenticator->authenticate($buasriId, $password)) {
            return null;
        }

        $person = $this->personnelDirectory->findActiveByBuasriId($buasriId);

        if ($person === null) {
            return null;
        }

        $email = $this->personnelDirectory->email($person);

        if ($email === null) {
            throw new RuntimeException('The authenticated person has no valid Google Workspace account in the SWU MIS API.');
        }

        $roleCodes = $this->roleCodesForPerson((int) $person->person_id);
        $role = $this->roleFromCodes($roleCodes);

        return [
            'pers_id' => (int) $person->person_id,
            'dept_id' => (int) ($person->dept_cd ?: $person->in_dept_cd),
            'buasri_id' => strtolower((string) $person->buasri_id),
            'name' => $this->personnelDirectory->name($person),
            'email' => $email,
            'label' => match ($role) {
                'officer' => 'เจ้าหน้าที่',
                default => 'บุคลากร',
            },
            'role' => $role,
            'available_roles' => collect(['user'])
                ->when($roleCodes->contains('OFFICER'), fn ($roles) => $roles->push('officer'))
                ->all(),
        ];
    }

    private function roleCodesForPerson(int $personId)
    {
        $database = DB::connection('course133');
        $roleCodes = $database
            ->table('service_portal.user_role_assignment as assignments')
            ->join('service_portal.app_role as roles', 'roles.role_id', '=', 'assignments.role_id')
            ->where('assignments.pers_id', $personId)
            ->where('assignments.is_active', 'Y')
            ->pluck('roles.role_code');

        if ($database->table('service_portal.form_officer_assignment')
            ->where('officer_pers_id', $personId)
            ->where('is_active', 'Y')
            ->exists()) {
            $roleCodes->push('OFFICER');
        }

        return $roleCodes->unique()->values();
    }

    private function roleFromCodes($roleCodes): string
    {
        if ($roleCodes->contains('OFFICER')) {
            return 'officer';
        }

        return 'user';
    }
}
