<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerReview extends ServicePortalModel
{
    protected $table = 'service_portal.officer_review';

    protected $primaryKey = 'officer_review_id';

    protected $casts = [
        'officer_pers_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }
}
