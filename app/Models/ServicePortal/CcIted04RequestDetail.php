<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CcIted04RequestDetail extends ServicePortalModel
{
    protected $table = 'service_portal.cc_ited_04_request_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    public $timestamps = true;

    protected $casts = [
        'requested_space_gb' => 'decimal:2',
        'granted_space_gb' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }
}
