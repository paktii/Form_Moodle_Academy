<?php

namespace App\Models\ServicePortal;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends ServicePortalModel
{
    protected $table = 'service_portal.service_request';

    protected $primaryKey = 'request_id';

    public $timestamps = true;

    protected $casts = [
        'requester_pers_id' => 'integer',
        'target_dept_id' => 'integer',
        'dean_pers_id' => 'integer',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'completed_by_pers_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_type_code', 'form_type_code');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class, 'status_code', 'status_code');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(RequestContact::class, 'request_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class, 'request_id');
    }

    public function deanReviews(): HasMany
    {
        return $this->hasMany(DeanReview::class, 'request_id');
    }

    public function officerReviews(): HasMany
    {
        return $this->hasMany(OfficerReview::class, 'request_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationOutbox::class, 'request_id');
    }

    public function ccIted01Detail(): HasOne
    {
        return $this->hasOne(WebsiteRequestDetail::class, 'request_id');
    }

    public function ccIted02Detail(): HasOne
    {
        return $this->hasOne(CcIted02RequestDetail::class, 'request_id');
    }

    public function ccIted04Detail(): HasOne
    {
        return $this->hasOne(CcIted04RequestDetail::class, 'request_id');
    }

    public function ccIted05Detail(): HasOne
    {
        return $this->hasOne(EmeetingRequestDetail::class, 'request_id');
    }

    public function ccIted06Detail(): HasOne
    {
        return $this->hasOne(MoodleCourseRequestDetail::class, 'request_id');
    }

    public function moodleCourseDetail(): HasOne
    {
        return $this->ccIted06Detail();
    }
}
