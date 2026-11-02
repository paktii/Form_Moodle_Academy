<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDocument extends ServicePortalModel
{
    protected $table = 'service_portal.request_document';

    protected $primaryKey = 'document_id';

    protected $casts = [
        'file_size_bytes' => 'integer',
        'version_no' => 'integer',
        'uploaded_by_pers_id' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_code', 'document_type_code');
    }
}
