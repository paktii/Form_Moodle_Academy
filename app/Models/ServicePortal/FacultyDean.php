<?php

namespace App\Models\ServicePortal;

class FacultyDean extends ServicePortalModel
{
    protected $table = 'service_portal.faculty_dean';

    protected $primaryKey = 'faculty_dean_id';

    public $timestamps = true;

    protected $casts = [
        'target_dept_id' => 'integer',
        'dean_pers_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
