<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseRequest extends Course133Model
{
    protected $table = 'course133.course_request';

    protected $primaryKey = 'request_id';

    public $timestamps = true;

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_code', 'project_type_code');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_code', 'category_code');
    }

    public function instructors(): HasMany
    {
        return $this->hasMany(CourseInstructor::class, 'request_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CourseDocument::class, 'request_id');
    }

    public function officerReviews(): HasMany
    {
        return $this->hasMany(OfficerReview::class, 'request_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CourseApproval::class, 'request_id');
    }
}
