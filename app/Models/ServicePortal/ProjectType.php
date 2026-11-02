<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectType extends ServicePortalModel
{
    protected $table = 'service_portal.project_type';

    protected $primaryKey = 'project_type_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = ['requires_detail' => 'boolean'];

    public function courseRequests(): HasMany
    {
        return $this->hasMany(MoodleCourseRequestDetail::class, 'project_type_code', 'project_type_code');
    }
}
