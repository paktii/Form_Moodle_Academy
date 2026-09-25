<?php

namespace App\Models;

class CourseDocument extends Course133Model
{
    protected $table = 'course133.course_document';

    protected $primaryKey = 'document_id';

    protected $casts = [
        'uploaded_at' => 'datetime',
        'roster_acknowledged_at' => 'datetime',
    ];
}
