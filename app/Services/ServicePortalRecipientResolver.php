<?php

namespace App\Services;

use App\Models\ServicePortal\FormNotificationRecipient;
use App\Models\ServicePortal\ServiceRequest;
use App\Models\ServicePortal\UserRoleAssignment;
use Illuminate\Support\Collection;
use RuntimeException;

class ServicePortalRecipientResolver
{
    public function __construct(private readonly PersonnelDirectory $personnelDirectory) {}

    /** @return array{pers_id: int, email: string, name: string} */
    public function requester(ServiceRequest $request): array
    {
        return $this->personRecipient((int) $request->requester_pers_id);
    }

    /** @return array<int, array{pers_id: int, email: string, name: string}> */
    public function roleRecipients(string $roleCode): array
    {
        $personIds = UserRoleAssignment::query()
            ->where('service_portal.user_role_assignment.is_active', 'Y')
            ->whereHas('role', fn ($query) => $query
                ->where('role_code', $roleCode)
                ->where('is_active', 'Y'))
            ->pluck('pers_id');

        return $this->personRecipients($personIds);
    }

    /** @return array<int, array{pers_id: int, email: string, name: string}> */
    public function formOfficerRecipients(ServiceRequest $request): array
    {
        return $this->assignmentRecipients(
            'service_portal.form_officer_assignment',
            'officer_pers_id',
            $request->form_type_code,
        );
    }

    /** @return array<int, array{pers_id: int, email: string, name: string}> */
    public function formHeadRecipients(ServiceRequest $request): array
    {
        if (app()->environment('local', 'uat')) {
            $testPersonId = (int) config('course-workflow.notifications.test_recipient_person_id');
            $testEmail = strtolower(trim((string) config('course-workflow.notifications.test_recipient_email')));

            if ($testPersonId <= 0 || ! filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid COURSE133_TEST_PERSON_ID and COURSE133_TEST_EMAIL are required for the test form head.');
            }

            return [[
                'pers_id' => $testPersonId,
                'email' => $testEmail,
                'name' => 'ผู้รับแจ้งเตือน Head สำหรับทดสอบ',
            ]];
        }

        return $this->assignmentRecipients(
            'service_portal.form_head_assignment',
            'head_pers_id',
            $request->form_type_code,
        );
    }

    /** @return array<int, array{pers_id: int|null, email: string, name: string|null}> */
    public function forFormEvent(ServiceRequest $request, string $notificationTypeCode): array
    {
        $rules = FormNotificationRecipient::query()
            ->where('form_type_code', $request->form_type_code)
            ->where('notification_type_code', $notificationTypeCode)
            ->where('is_active', 'Y')
            ->get();

        $recipients = collect();

        foreach ($rules as $rule) {
            if ($rule->recipient_source === 'APP_ROLE') {
                $personIds = UserRoleAssignment::query()
                    ->where('role_id', $rule->role_id)
                    ->where('is_active', 'Y')
                    ->whereHas('role', fn ($query) => $query->where('is_active', 'Y'))
                    ->pluck('pers_id');

                $recipients->push(...$this->personRecipients($personIds));
            } elseif ($rule->recipient_source === 'PERSON') {
                $recipients->push($this->personRecipient((int) $rule->recipient_pers_id));
            } elseif ($rule->recipient_source === 'STATIC_EMAIL') {
                $recipients->push([
                    'pers_id' => null,
                    'email' => strtolower(trim((string) $rule->recipient_email)),
                    'name' => $this->nullableString($rule->recipient_name),
                ]);
            }
        }

        return $recipients
            ->filter(fn (array $recipient) => filter_var($recipient['email'], FILTER_VALIDATE_EMAIL))
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values()
            ->all();
    }

    /** @param Collection<int, int|string> $personIds
     * @return array<int, array{pers_id: int, email: string, name: string}>
     */
    private function personRecipients(Collection $personIds): array
    {
        return $personIds
            ->map(fn ($personId) => $this->personRecipient((int) $personId))
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values()
            ->all();
    }

    /** @return array<int, array{pers_id: int, email: string, name: string}> */
    private function assignmentRecipients(string $table, string $personColumn, string $formTypeCode): array
    {
        $personIds = \Illuminate\Support\Facades\DB::connection('course133')
            ->table($table)
            ->where('form_type_code', $formTypeCode)
            ->where('is_active', 'Y')
            ->pluck($personColumn);

        return $this->personRecipients($personIds);
    }

    /** @return array{pers_id: int, email: string, name: string} */
    private function personRecipient(int $personId): array
    {
        $person = $this->personnelDirectory->findByPersonId($personId);
        $email = $person ? $this->personnelDirectory->email($person) : null;

        if ($person === null || $email === null) {
            throw new RuntimeException("Cannot resolve a valid recipient for person ID {$personId}.");
        }

        return [
            'pers_id' => $personId,
            'email' => $email,
            'name' => $this->personnelDirectory->name($person),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
