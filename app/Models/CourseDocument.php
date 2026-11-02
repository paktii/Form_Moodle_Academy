<?php

namespace App\Models;

class CourseDocument extends Course133Model
{
    protected $table = 'service_portal.request_document';

    protected $primaryKey = 'document_id';

    protected $casts = [
        'uploaded_at' => 'datetime',
        'roster_acknowledged_at' => 'datetime',
    ];

    public function getDocumentTypeAttribute(): ?string
    {
        return $this->attributes['document_type_code'] ?? null;
    }

    public function setDocumentTypeAttribute(string $value): void
    {
        $this->attributes['document_type_code'] = $value;
    }
}
