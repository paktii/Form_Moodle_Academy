<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FormType extends ServicePortalModel
{
    protected $table = 'service_portal.form_type';

    protected $primaryKey = 'form_type_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $casts = [
        'current_version' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'form_type_code', 'form_type_code');
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(FormNotificationRecipient::class, 'form_type_code', 'form_type_code');
    }
}
