<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsitePlatform extends ServicePortalModel
{
    protected $table = 'service_portal.website_platform';

    protected $primaryKey = 'platform_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'requires_detail' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function requestDetails(): HasMany
    {
        return $this->hasMany(WebsiteRequestDetail::class, 'platform_code', 'platform_code');
    }
}
