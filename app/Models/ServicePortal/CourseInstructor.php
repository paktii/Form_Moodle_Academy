<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseInstructor extends ServicePortalModel
{
    protected $table = 'service_portal.course_instructor';

    protected $primaryKey = 'instructor_id';

    protected $casts = [
        'pers_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function courseRequest(): BelongsTo
    {
        return $this->belongsTo(MoodleCourseRequestDetail::class, 'request_id', 'request_id');
    }
}
