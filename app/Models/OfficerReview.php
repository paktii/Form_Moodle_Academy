<?php

namespace App\Models;

class OfficerReview extends Course133Model
{
    protected $table = 'course133.officer_review';

    protected $primaryKey = 'officer_review_id';

    protected $casts = ['reviewed_at' => 'datetime'];
}
