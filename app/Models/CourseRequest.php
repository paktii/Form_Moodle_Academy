<?php

namespace App\Models;

use App\Models\ServicePortal\MoodleCourseRequestDetail;
use App\Models\ServicePortal\RequestContact;
use App\Models\ServicePortal\CcIted02RequestDetail;
use App\Models\ServicePortal\CcIted04RequestDetail;
use App\Models\ServicePortal\EmeetingRequestDetail;
use App\Models\ServicePortal\WebsiteRequestDetail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CourseRequest extends Course133Model
{
    protected $table = 'service_portal.service_request';

    protected $primaryKey = 'request_id';

    public $timestamps = true;

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function moodleCourseDetail(): HasOne
    {
        return $this->hasOne(MoodleCourseRequestDetail::class, 'request_id');
    }

    public function websiteDetail(): HasOne
    {
        return $this->hasOne(WebsiteRequestDetail::class, 'request_id');
    }

    public function websiteContact(): HasOne
    {
        return $this->hasOne(RequestContact::class, 'request_id')
            ->where('contact_role_code', 'WEBSITE_CONTACT')
            ->where('is_primary', true);
    }

    public function websiteContactChangeDetail(): HasOne
    {
        return $this->hasOne(CcIted02RequestDetail::class, 'request_id');
    }

    public function websiteStorageDetail(): HasOne
    {
        return $this->hasOne(CcIted04RequestDetail::class, 'request_id');
    }

    public function emeetingDetail(): HasOne
    {
        return $this->hasOne(EmeetingRequestDetail::class, 'request_id');
    }

    public function emeetingContact(): HasOne
    {
        return $this->hasOne(RequestContact::class, 'request_id')
            ->where('contact_role_code', 'EMEETING_CONTACT')
            ->where('is_primary', true);
    }

    public function currentEmeetingContact(): HasOne
    {
        return $this->hasOne(RequestContact::class, 'request_id')
            ->where('contact_role_code', 'CURRENT_EMEETING_CONTACT')
            ->where('is_primary', true);
    }

    public function currentWebsiteContact(): HasOne
    {
        return $this->hasOne(RequestContact::class, 'request_id')
            ->where('contact_role_code', 'CURRENT_WEBSITE_CONTACT')
            ->where('is_primary', true);
    }

    public function newWebsiteContact(): HasOne
    {
        return $this->hasOne(RequestContact::class, 'request_id')
            ->where('contact_role_code', 'NEW_WEBSITE_CONTACT')
            ->where('is_primary', true);
    }

    public function coordinatorContact(): HasOne
    {
        return $this->hasOne(RequestContact::class, 'request_id')
            ->where('contact_role_code', 'COORDINATOR')
            ->where('is_primary', true);
    }

    public function instructors(): HasMany
    {
        return $this->hasMany(CourseInstructor::class, 'request_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CourseDocument::class, 'request_id');
    }

    public function officerReviews(): HasMany
    {
        return $this->hasMany(OfficerReview::class, 'request_id');
    }

    public function deanReviews(): HasMany
    {
        return $this->hasMany(DeanReview::class, 'request_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationOutbox::class, 'request_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id');
    }

    public function getStatusAttribute(): ?string
    {
        return $this->attributes['status_code'] ?? null;
    }

    public function setStatusAttribute(string $value): void
    {
        $this->attributes['status_code'] = $value;
    }

    public function getProjectTypeAttribute()
    {
        return $this->moodleCourseDetail?->projectType;
    }

    public function getCategoryAttribute()
    {
        return $this->moodleCourseDetail?->category;
    }

    public function getAttribute($key)
    {
        $detailFields = [
            'project_name', 'project_type_code', 'project_other', 'course_name_th', 'course_name_en',
            'category_code', 'category_other', 'course_description', 'learning_mode', 'activity_round',
            'activity_phase', 'starts_on', 'ends_on', 'enrollment_method', 'enrollment_other',
            'expected_students', 'course_id',
        ];

        if (in_array($key, $detailFields, true)) {
            return $this->moodleCourseDetail?->getAttribute($key);
        }

        if ($key === 'coordinator_first_name' || $key === 'coordinator_last_name') {
            $contact = parent::getAttribute('coordinatorContact');
            $parts = preg_split('/\s+/u', trim((string) $contact?->full_name), 2) ?: [];

            return $key === 'coordinator_first_name' ? ($parts[0] ?? '') : ($parts[1] ?? '');
        }

        $contactFields = [
            'coordinator_position' => 'position_name',
            'coordinator_phone' => 'phone',
            'coordinator_email' => 'email',
        ];
        if (isset($contactFields[$key])) {
            $contact = parent::getAttribute('coordinatorContact');
            return $contact?->getAttribute($contactFields[$key]);
        }

        return parent::getAttribute($key);
    }
}
