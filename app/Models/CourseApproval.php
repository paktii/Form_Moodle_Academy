<?php

namespace App\Models;

class CourseApproval extends Course133Model
{
    protected $table = 'course133.course_approval';

    protected $primaryKey = 'approval_id';

    protected $casts = ['decided_at' => 'datetime'];
}
