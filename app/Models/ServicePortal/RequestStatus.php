<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestStatus extends ServicePortalModel
{
    protected $table = 'service_portal.request_status';

    protected $primaryKey = 'status_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'is_terminal' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'status_code', 'status_code');
    }
}
