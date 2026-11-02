<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactRole extends ServicePortalModel
{
    protected $table = 'service_portal.contact_role';

    protected $primaryKey = 'contact_role_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public function contacts(): HasMany
    {
        return $this->hasMany(RequestContact::class, 'contact_role_code', 'contact_role_code');
    }
}
