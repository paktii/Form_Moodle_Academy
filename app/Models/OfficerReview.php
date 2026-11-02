<?php

namespace App\Models;

class OfficerReview extends Course133Model
{
    protected $table = 'service_portal.officer_review';

    protected $primaryKey = 'officer_review_id';

    protected $casts = ['reviewed_at' => 'datetime'];
}
