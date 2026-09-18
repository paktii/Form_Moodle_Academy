<?php

namespace App\Models;

class NotificationOutbox extends Course133Model
{
    protected $table = 'course133.notification_outbox';

    protected $primaryKey = 'notification_id';

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
