<?php

namespace App\Models;

class CourseCategory extends Course133Model
{
    protected $table = 'service_portal.course_category';

    protected $primaryKey = 'category_code';

    public $incrementing = false;

    protected $keyType = 'string';
}
