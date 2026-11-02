<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleAssignment extends ServicePortalModel
{
    protected $table = 'service_portal.user_role_assignment';

    protected $primaryKey = 'role_assignment_id';

    public $timestamps = true;

    protected $casts = [
        'pers_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(AppRole::class, 'role_id');
    }
}
