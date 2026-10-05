<?php

namespace App\Services;

class PersonnelDirectory
{
    /** @var array<int, object|null> */
    private array $peopleById = [];

    public function __construct(private readonly SwuMisApiClient $api) {}

    public function findActiveByBuasriId(string $buasriId): ?object
    {
        $normalizedId = strtolower(trim($buasriId));
        $person = collect($this->api->search(
            (string) config('swu_mis_api.endpoints.officers', 'Officer'),
            'buasri',
            $normalizedId,
        ))->first(fn (array $record) => strtolower(trim((string) ($record['buasriId'] ?? ''))) === $normalizedId
            && strtoupper(trim((string) ($record['activeFlag'] ?? ''))) === 'A'
        );

        return is_array($person) ? $this->mapPerson($person) : null;
    }

    public function findByPersonId(int $personId): ?object
    {
        if (array_key_exists($personId, $this->peopleById)) {
            return $this->peopleById[$personId];
        }

        $person = collect($this->api->search(
            (string) config('swu_mis_api.endpoints.officers', 'Officer'),
            'personid',
            (string) $personId,
        ))->first(fn (array $record) => (int) ($record['personId'] ?? 0) === $personId);

        return $this->peopleById[$personId] = is_array($person)
            ? $this->mapPerson($person)
            : null;
    }

    public function email(object $person): ?string
    {
        $account = trim((string) $person->gafe_account);

        if ($account === '') {
            $account = trim((string) $person->buasri_id);
        }

        $email = str_contains($account, '@') ? $account : $account.'@g.swu.ac.th';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }

    public function name(object $person): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $person->prename_lname_th),
            trim((string) $person->person_fname_th),
            trim((string) $person->person_lname_th),
        ])));
    }

    /** @return array<string, string> */
    public function coordinatorDefaults(object $person): array
    {
        $phone = collect([
            $person->in_telephone_no,
            $person->did_phone_no,
            $person->mobile_phone,
        ])->map(fn ($value) => trim((string) $value))->first(fn (string $value) => $value !== '');

        return [
            'coordinator_first' => trim((string) $person->person_fname_th),
            'coordinator_last' => trim((string) $person->person_lname_th),
            'coordinator_position' => trim((string) $person->position),
            'coordinator_phone' => $phone ?? '',
            'coordinator_email' => $this->email($person) ?? '',
        ];
    }

    /** @param array<string, mixed> $record */
    private function mapPerson(array $record): object
    {
        $facultyId = $this->positiveInt($record['facultyId'] ?? null);
        $departmentId = $this->positiveInt($record['departmentId'] ?? null);

        return (object) [
            'person_id' => (int) ($record['personId'] ?? 0),
            'buasri_id' => strtolower(trim((string) ($record['buasriId'] ?? ''))),
            'gafe_account' => trim((string) ($record['gafeMail'] ?? '')),
            'prename_lname_th' => trim((string) ($record['titleTh'] ?? $record['stitleTh'] ?? '')),
            'person_fname_th' => trim((string) ($record['nameTh'] ?? '')),
            'person_lname_th' => trim((string) ($record['lnameTh'] ?? '')),
            'position' => trim((string) ($record['positionTh'] ?? '')),
            'dept_cd' => $departmentId ?? $facultyId,
            'in_dept_cd' => $facultyId,
            'division' => trim((string) ($record['departmentTh'] ?? $record['facultyTh'] ?? '')),
            'active_flag' => strtoupper(trim((string) ($record['activeFlag'] ?? ''))),
            'in_telephone_no' => '',
            'did_phone_no' => '',
            'mobile_phone' => '',
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $number = (int) $value;

        return $number > 0 ? $number : null;
    }
}
