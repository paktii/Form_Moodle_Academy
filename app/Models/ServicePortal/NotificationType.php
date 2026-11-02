<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationType extends ServicePortalModel
{
    protected $table = 'service_portal.notification_type';

    protected $primaryKey = 'notification_type_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationOutbox::class, 'notification_type_code', 'notification_type_code');
    }

    public function recipientRules(): HasMany
    {
        return $this->hasMany(FormNotificationRecipient::class, 'notification_type_code', 'notification_type_code');
    }
}
