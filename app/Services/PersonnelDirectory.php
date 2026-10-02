<?php

namespace App\Services;

use App\Models\SalaPerson;

class PersonnelDirectory
{
    /** @var array<int, SalaPerson|null> */
    private array $peopleById = [];

    public function findActiveByBuasriId(string $buasriId): ?SalaPerson
    {
        return SalaPerson::query()
            ->where('buasri_id', $buasriId)
            ->where('active_flag', 'A')
            ->orderByDesc('person_id')
            ->first();
    }

    public function findByPersonId(int $personId): ?SalaPerson
    {
        return $this->peopleById[$personId] ??= SalaPerson::query()->find($personId);
    }

    public function email(SalaPerson $person): ?string
    {
        $account = trim((string) $person->gafe_account);

        if ($account === '') {
            $account = trim((string) $person->buasri_id);
        }

        $email = str_contains($account, '@') ? $account : $account.'@g.swu.ac.th';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
    }

    public function name(SalaPerson $person): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $person->prename_lname_th),
            trim((string) $person->person_fname_th),
            trim((string) $person->person_lname_th),
        ])));
    }

    /** @return array<string, string> */
    public function coordinatorDefaults(SalaPerson $person): array
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
}
