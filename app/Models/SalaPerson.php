<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaPerson extends Model
{
    protected $connection = 'saladb';

    protected $table = 'dbo.persons';

    protected $primaryKey = 'person_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
