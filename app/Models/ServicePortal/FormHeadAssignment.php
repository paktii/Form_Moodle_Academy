<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormHeadAssignment extends ServicePortalModel
{
    protected $table = 'service_portal.form_head_assignment';

    protected $primaryKey = 'head_assignment_id';

    public $timestamps = true;

    protected $casts = [
        'head_pers_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_code', 'form_type_code');
    }
}

