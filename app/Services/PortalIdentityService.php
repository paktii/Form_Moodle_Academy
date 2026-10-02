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
            throw new RuntimeException('The authenticated person has no valid Google Workspace account in Server 199.');
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
                'approver' => 'ผู้อนุมัติ',
                default => 'บุคลากร',
            },
            'role' => $role,
            'available_roles' => collect(['user'])
                ->when($roleCodes->contains('OFFICER'), fn ($roles) => $roles->push('officer'))
                ->when($roleCodes->contains('APPROVER'), fn ($roles) => $roles->push('approver'))
                ->all(),
        ];
    }

    private function roleCodesForPerson(int $personId)
    {
        return DB::connection('course133')
            ->table('course133.user_role_assignment as assignments')
            ->join('course133.app_role as roles', 'roles.role_id', '=', 'assignments.role_id')
            ->where('assignments.pers_id', $personId)
            ->where('assignments.is_active', true)
            ->pluck('roles.role_code');
    }

    private function roleFromCodes($roleCodes): string
    {
        if ($roleCodes->contains('APPROVER')) {
            return 'approver';
        }

        if ($roleCodes->contains('OFFICER')) {
            return 'officer';
        }

        return 'user';
    }
}
