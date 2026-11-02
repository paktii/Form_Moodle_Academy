<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends ServicePortalModel
{
    protected $table = 'service_portal.course_category';

    protected $primaryKey = 'category_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public function courseRequests(): HasMany
    {
        return $this->hasMany(MoodleCourseRequestDetail::class, 'category_code', 'category_code');
    }
}
