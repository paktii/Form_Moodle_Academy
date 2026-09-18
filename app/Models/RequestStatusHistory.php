<?php

namespace App\Models;

class RequestStatusHistory extends Course133Model
{
    protected $table = 'course133.request_status_history';

    protected $primaryKey = 'history_id';

    protected $casts = ['changed_at' => 'datetime'];
}
