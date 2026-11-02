<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteRequestDetail extends ServicePortalModel
{
    protected $table = 'service_portal.cc_ited_01_request_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    public $timestamps = true;

    protected $casts = [
        'service_starts_on' => 'date',
        'service_ends_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(WebsitePlatform::class, 'platform_code', 'platform_code');
    }
}
