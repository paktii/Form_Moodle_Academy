<?php

namespace App\Models;

class FacultyDean extends Course133Model
{
    protected $table = 'service_portal.faculty_dean';

    protected $primaryKey = 'faculty_dean_id';

    public $timestamps = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
