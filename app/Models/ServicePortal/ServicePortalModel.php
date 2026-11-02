<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Model;

abstract class ServicePortalModel extends Model
{
    protected $connection = 'course133';

    public $timestamps = false;

    protected $guarded = [];
}
