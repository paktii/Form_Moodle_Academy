<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestContact extends ServicePortalModel
{
    protected $table = 'service_portal.request_contact';

    protected $primaryKey = 'contact_id';

    protected $casts = [
        'pers_id' => 'integer',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ContactRole::class, 'contact_role_code', 'contact_role_code');
    }
}
