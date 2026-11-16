<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

    public function findActiveByName(string $name): ?object
    {
        $normalizedName = $this->normalizedName($name);
        if ($normalizedName === '') {
            return null;
        }

        $people = $this->activePeopleByName($name);

        $exactMatches = $people->filter(function (object $person) use ($normalizedName): bool {
            $fullName = $this->normalizedName($this->name($person));
            $nameWithoutTitle = $this->normalizedName(trim($person->person_fname_th.' '.$person->person_lname_th));

            return $fullName === $normalizedName || $nameWithoutTitle === $normalizedName;
        })->values();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        return $people->count() === 1 ? $people->first() : null;
    }

    /** @return array<int, object> */
    public function searchActive(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        if (! preg_match('/[^\x20-\x7E]/u', $query) && ! str_contains($query, ' ')) {
            $person = $this->findActiveByBuasriId(strtolower(strtok($query, '@')));

            return $person === null ? [] : [$person];
        }

        return $this->activePeopleByName($query)
            ->take(max(1, $limit))
            ->all();
    }

    /** @return array<int, object> */
    public function searchActiveInDepartment(int $departmentId, string $query, int $limit = 15): array
    {
        $query = $this->normalizedName($query);
        if ($departmentId <= 0 || $query === '') {
            return [];
        }

        $records = Cache::remember(
            'swu-mis:officers:faculty:'.$departmentId,
            now()->addMinutes(10),
            fn () => $this->api->searchAll(
                (string) config('swu_mis_api.endpoints.officers', 'Officer'),
                'facultyid',
                (string) $departmentId,
            ),
        );

        return collect($records)
            ->filter(fn (array $record) => strtoupper(trim((string) ($record['activeFlag'] ?? ''))) === 'A'
                && (int) ($record['facultyId'] ?? 0) === $departmentId)
            ->map(fn (array $record) => $this->mapPerson($record))
            ->filter(function (object $person) use ($query): bool {
                $searchable = $this->normalizedName(implode(' ', array_filter([
                    $this->name($person),
                    $person->person_fname_th,
                    $person->person_lname_th,
                    $person->buasri_id,
                    $person->division,
                ])));

                return str_contains($searchable, $query);
            })
            ->unique('person_id')
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    /** @return Collection<int, object> */
    private function activePeopleByName(string $name): Collection
    {
        $normalizedName = $this->normalizedName($name);
        $records = $this->api->search(
            (string) config('swu_mis_api.endpoints.officers', 'Officer'),
            'fullname',
            trim($name),
        );

        if ($records === [] && str_contains($normalizedName, ' ')) {
            $nameParts = preg_split('/\s+/u', $normalizedName, -1, PREG_SPLIT_NO_EMPTY);
            $records = $this->api->search(
                (string) config('swu_mis_api.endpoints.officers', 'Officer'),
                'fullname',
                (string) end($nameParts),
            );
        }

        $people = collect($records)
            ->filter(fn (array $record) => strtoupper(trim((string) ($record['activeFlag'] ?? ''))) === 'A')
            ->map(fn (array $record) => $this->mapPerson($record))
            ->unique('person_id')
            ->values();

        return $people;
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
        return $this->workspaceEmail($person);
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
            'coordinator_email' => $this->workspaceEmail($person) ?? '',
        ];
    }

    private function workspaceEmail(object $person): ?string
    {
        foreach ([$person->gafe_account, $person->buasri_id] as $account) {
            $account = trim((string) $account);
            if ($account === '') {
                continue;
            }

            $email = strtolower(str_contains($account, '@') ? $account : $account.'@g.swu.ac.th');
            if (str_ends_with($email, '@g.swu.ac.th') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
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

    private function normalizedName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)), 'UTF-8');
    }
}
