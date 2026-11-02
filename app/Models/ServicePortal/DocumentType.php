<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends ServicePortalModel
{
    protected $table = 'service_portal.document_type';

    protected $primaryKey = 'document_type_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = ['max_file_size_bytes' => 'integer'];

    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class, 'document_type_code', 'document_type_code');
    }
}
