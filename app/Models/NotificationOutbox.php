<?php

namespace App\Models;

class NotificationOutbox extends Course133Model
{
    protected $table = 'course133.notification_outbox';

    protected $primaryKey = 'notification_id';

    protected $casts = [
        'processing_started_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
