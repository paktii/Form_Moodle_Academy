<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormOfficerAssignment extends ServicePortalModel
{
    protected $table = 'service_portal.form_officer_assignment';

    protected $primaryKey = 'officer_assignment_id';

    public $timestamps = true;

    protected $casts = [
        'officer_pers_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_code', 'form_type_code');
    }
}

