<?php

namespace App\Models;

class CourseCategory extends Course133Model
{
    protected $table = 'course133.course_category';

    protected $primaryKey = 'category_code';

    public $incrementing = false;

    protected $keyType = 'string';
}
