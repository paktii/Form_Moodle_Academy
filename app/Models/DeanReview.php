<?php

namespace App\Models;

class DeanReview extends Course133Model
{
    protected $table = 'course133.dean_review';

    protected $primaryKey = 'dean_review_id';

    protected $casts = ['decided_at' => 'datetime'];
}
