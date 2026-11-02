<?php

namespace App\Models;

class RequestStatusHistory extends Course133Model
{
    protected $table = 'service_portal.request_status_history';

    public function getStatusAttribute(): ?string
    {
        return $this->attributes['to_status_code'] ?? null;
    }

    protected $primaryKey = 'history_id';

    protected $casts = ['changed_at' => 'datetime'];
}
