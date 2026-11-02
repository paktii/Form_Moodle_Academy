<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmeetingRequestDetail extends ServicePortalModel
{
    protected $table = 'service_portal.cc_ited_05_request_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    public $timestamps = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }
}
