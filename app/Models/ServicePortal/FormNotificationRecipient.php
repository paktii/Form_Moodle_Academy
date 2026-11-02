<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormNotificationRecipient extends ServicePortalModel
{
    protected $table = 'service_portal.form_notification_recipient';

    protected $primaryKey = 'recipient_rule_id';

    protected $casts = ['recipient_pers_id' => 'integer'];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_code', 'form_type_code');
    }

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_code', 'notification_type_code');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AppRole::class, 'role_id');
    }
}
