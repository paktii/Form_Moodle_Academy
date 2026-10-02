<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaDepartment extends Model
{
    protected $connection = 'saladb';

    protected $table = 'dbo.departments';

    protected $primaryKey = 'dept_cd';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
