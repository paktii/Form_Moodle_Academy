<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStatusHistory extends ServicePortalModel
{
    protected $table = 'service_portal.request_status_history';

    protected $primaryKey = 'history_id';

    protected $casts = [
        'changed_by_pers_id' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'from_status_code', 'status_code');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'to_status_code', 'status_code');
    }
}
