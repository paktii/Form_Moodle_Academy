<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoodleCourseRequestDetail extends ServicePortalModel
{
    protected $table = 'service_portal.cc_ited_06_request_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    public $timestamps = true;

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'expected_students' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

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
}
