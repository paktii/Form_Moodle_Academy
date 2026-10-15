<?php

namespace App\Models;

class ApproverReview extends Course133Model
{
    protected $table = 'course133.approver_review';

    protected $primaryKey = 'approver_review_id';

    protected $casts = ['decided_at' => 'datetime'];
}
