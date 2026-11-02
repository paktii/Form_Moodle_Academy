<?php

namespace App\Models;

class NotificationOutbox extends Course133Model
{
    protected $table = 'service_portal.notification_outbox';

    protected $primaryKey = 'notification_id';

    protected $casts = [
        'claimed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function getNotificationTypeAttribute(): ?string
    {
        return $this->attributes['notification_type_code'] ?? null;
    }

    public function setNotificationTypeAttribute(string $value): void
    {
        $this->attributes['notification_type_code'] = $value;
    }

    public function getProcessingStartedAtAttribute()
    {
        return $this->claimed_at;
    }

    public function setProcessingStartedAtAttribute($value): void
    {
        $this->attributes['claimed_at'] = $value;
    }
}
