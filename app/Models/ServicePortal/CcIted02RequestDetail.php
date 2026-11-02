<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CcIted02RequestDetail extends ServicePortalModel
{
    protected $table = 'service_portal.cc_ited_02_request_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    public $timestamps = true;

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }
}
