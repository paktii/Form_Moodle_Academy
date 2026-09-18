<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class Course133Model extends Model
{
    protected $connection = 'course133';

    public $timestamps = false;

    protected $guarded = [];
}
