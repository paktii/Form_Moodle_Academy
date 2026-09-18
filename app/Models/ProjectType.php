<?php

namespace App\Models;

class ProjectType extends Course133Model
{
    protected $table = 'course133.project_type';

    protected $primaryKey = 'project_type_code';

    public $incrementing = false;

    protected $keyType = 'string';
}
