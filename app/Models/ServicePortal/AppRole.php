<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AppRole extends ServicePortalModel
{
    protected $table = 'service_portal.app_role';

    protected $primaryKey = 'role_id';

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class, 'role_id');
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(FormNotificationRecipient::class, 'role_id');
    }
}
