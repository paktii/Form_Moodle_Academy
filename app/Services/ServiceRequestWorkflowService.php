<?php

namespace App\Services;

use App\Models\ServicePortal\DeanReview;
use App\Models\ServicePortal\NotificationOutbox;
use App\Models\ServicePortal\OfficerReview;
use App\Models\ServicePortal\RequestStatusHistory;
use App\Models\ServicePortal\ServiceRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

class ServiceRequestWorkflowService
{
    public function __construct(
        private readonly ServicePortalRecipientResolver $recipientResolver,
    ) {}

    public function approveByDean(int $requestId, int $deanPersId, ?string $comment = null): ServiceRequest
    {
        $officerRecipients = $this->requiredRecipients(
            $this->recipientResolver->roleRecipients('OFFICER'),
            'No active officer recipient is configured.',
        );

        return DB::connection('course133')->transaction(function () use ($requestId, $deanPersId, $comment, $officerRecipients) {
            $request = $this->lockedRequest($requestId, 'PENDING_DEAN_REVIEW');
            $this->assertAssignedDean($request, $deanPersId);

            DeanReview::query()->create([
                'request_id' => $request->request_id,
                'dean_pers_id' => $deanPersId,
                'decision' => 'APPROVED',
                'comment' => $this->nullableString($comment),
            ]);

            $this->transition($request, 'UNDER_OFFICER_REVIEW', $deanPersId, 'DEAN', $comment);
            $this->queueNotifications($request, 'OFFICER_REVIEW_REQUIRED', $officerRecipients);

            return $request;
        });
    }

    public function rejectByDean(int $requestId, int $deanPersId, string $comment): ServiceRequest
    {
        $snapshot = ServiceRequest::query()->findOrFail($requestId);
        $requester = $this->recipientResolver->requester($snapshot);
        $comment = $this->requiredString($comment, 'A rejection comment is required.');

        return DB::connection('course133')->transaction(function () use ($requestId, $deanPersId, $comment, $requester) {
            $request = $this->lockedRequest($requestId, 'PENDING_DEAN_REVIEW');
            $this->assertAssignedDean($request, $deanPersId);

            DeanReview::query()->create([
                'request_id' => $request->request_id,
                'dean_pers_id' => $deanPersId,
                'decision' => 'REJECTED',
                'comment' => $comment,
            ]);

            $this->transition($request, 'REJECTED', $deanPersId, 'DEAN', $comment);
            $this->queueNotifications($request, 'DEAN_REJECTED', [$requester]);

            return $request;
        });
    }

    /** @param array<string, mixed> $result */
    public function approveByOfficer(int $requestId, int $officerPersId, array $result = []): ServiceRequest
    {
        $snapshot = ServiceRequest::query()->findOrFail($requestId);
        $requester = $this->recipientResolver->requester($snapshot);

        return DB::connection('course133')->transaction(function () use ($requestId, $officerPersId, $result, $requester) {
            $request = $this->lockedRequest($requestId, 'UNDER_OFFICER_REVIEW');

            $this->storeOfficerResult($request, $result);

            OfficerReview::query()->create([
                'request_id' => $request->request_id,
                'officer_pers_id' => $officerPersId,
                'decision' => 'APPROVED',
                'rejection_reason' => null,
            ]);

            $fromStatus = $request->status_code;
            $request->update([
                'status_code' => 'COMPLETED',
                'completed_at' => now(),
                'completed_by_pers_id' => $officerPersId,
            ]);
            $this->addHistory($request, $fromStatus, 'COMPLETED', $officerPersId, 'OFFICER');
            $this->queueNotifications($request, 'REQUEST_COMPLETED_REQUESTER', [$requester]);

            return $request;
        });
    }

    public function rejectByOfficer(int $requestId, int $officerPersId, string $reason): ServiceRequest
    {
        $snapshot = ServiceRequest::query()->findOrFail($requestId);
        $requester = $this->recipientResolver->requester($snapshot);
        $reason = $this->requiredString($reason, 'A rejection reason is required.');

        return DB::connection('course133')->transaction(function () use ($requestId, $officerPersId, $reason, $requester) {
            $request = $this->lockedRequest($requestId, 'UNDER_OFFICER_REVIEW');

            OfficerReview::query()->create([
                'request_id' => $request->request_id,
                'officer_pers_id' => $officerPersId,
                'decision' => 'REJECTED',
                'rejection_reason' => $reason,
            ]);

            $this->transition($request, 'REJECTED', $officerPersId, 'OFFICER', $reason);
            $this->queueNotifications($request, 'REQUEST_REJECTED', [$requester]);

            return $request;
        });
    }

    private function lockedRequest(int $requestId, string $expectedStatus): ServiceRequest
    {
        $request = ServiceRequest::query()->lockForUpdate()->findOrFail($requestId);

        if ($request->status_code !== $expectedStatus) {
            throw new DomainException("Request {$requestId} must be in {$expectedStatus} status.");
        }

        return $request;
    }

    private function assertAssignedDean(ServiceRequest $request, int $deanPersId): void
    {
        if ((int) $request->dean_pers_id !== $deanPersId) {
            throw new DomainException('Only the assigned dean may review this request.');
        }
    }

    /** @param array<string, mixed> $result */
    private function storeOfficerResult(ServiceRequest $request, array $result): void
    {
        match ($request->form_type_code) {
            'CC-ITED-01' => $request->ccIted01Detail()->lockForUpdate()->firstOrFail()->update([
                'allocated_domain' => $this->requiredString(
                    $result['allocated_domain'] ?? null,
                    'allocated_domain is required for CC-ITED-01.',
                ),
            ]),
            'CC-ITED-02' => true,
            'CC-ITED-04' => $request->ccIted04Detail()->lockForUpdate()->firstOrFail()->update([
                'granted_space_gb' => 1,
                'execution_detail' => $this->requiredString(
                    $result['execution_detail'] ?? null,
                    'execution_detail is required for CC-ITED-04.',
                ),
            ]),
            'CC-ITED-05' => $request->ccIted05Detail()->lockForUpdate()->firstOrFail()->update([
                'execution_detail' => $this->requiredString(
                    $result['execution_detail'] ?? null,
                    'execution_detail is required for CC-ITED-05.',
                ),
            ]),
            'CC-ITED-06' => $request->ccIted06Detail()->lockForUpdate()->firstOrFail()->update([
                'course_id' => $this->requiredString(
                    $result['course_id'] ?? null,
                    'course_id is required for CC-ITED-06.',
                ),
            ]),
            default => throw new DomainException("Unsupported form type {$request->form_type_code}."),
        };
    }

    private function transition(
        ServiceRequest $request,
        string $toStatus,
        int $actorPersId,
        string $source,
        ?string $comment = null,
    ): void {
        $fromStatus = $request->status_code;
        $request->update(['status_code' => $toStatus]);
        $this->addHistory($request, $fromStatus, $toStatus, $actorPersId, $source, $comment);
    }

    private function addHistory(
        ServiceRequest $request,
        string $fromStatus,
        string $toStatus,
        int $actorPersId,
        string $source,
        ?string $comment = null,
    ): void {
        RequestStatusHistory::query()->create([
            'request_id' => $request->request_id,
            'from_status_code' => $fromStatus,
            'to_status_code' => $toStatus,
            'changed_by_pers_id' => $actorPersId,
            'change_source' => $source,
            'comment' => $this->nullableString($comment),
        ]);
    }

    /** @param array<int, array{pers_id: int|null, email: string, name: string|null}> $recipients */
    private function queueNotifications(ServiceRequest $request, string $type, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            NotificationOutbox::query()->create([
                'request_id' => $request->request_id,
                'notification_type_code' => $type,
                'recipient_pers_id' => $recipient['pers_id'],
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'],
                'delivery_status' => 'PENDING',
                'attempt_count' => 0,
            ]);
        }
    }

    /** @param array<int, array{pers_id: int|null, email: string, name: string|null}> $recipients
     * @return array<int, array{pers_id: int|null, email: string, name: string|null}>
     */
    private function requiredRecipients(array $recipients, string $message): array
    {
        if ($recipients === []) {
            throw new DomainException($message);
        }

        return $recipients;
    }

    private function requiredString(mixed $value, string $message): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new DomainException($message);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function positiveDecimal(mixed $value, string $message): string
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            throw new DomainException($message);
        }

        return number_format((float) $value, 2, '.', '');
    }
}
