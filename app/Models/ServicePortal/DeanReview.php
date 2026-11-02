<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeanReview extends ServicePortalModel
{
    protected $table = 'service_portal.dean_review';

    protected $primaryKey = 'dean_review_id';

    protected $casts = [
        'dean_pers_id' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }
}
