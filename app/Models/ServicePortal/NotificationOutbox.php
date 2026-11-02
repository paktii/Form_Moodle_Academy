<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationOutbox extends ServicePortalModel
{
    protected $table = 'service_portal.notification_outbox';

    protected $primaryKey = 'notification_id';

    protected $casts = [
        'recipient_pers_id' => 'integer',
        'attempt_count' => 'integer',
        'next_attempt_at' => 'datetime',
        'claimed_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_code', 'notification_type_code');
    }
}
