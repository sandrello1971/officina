<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentCourse extends Pivot
{
    use HasFactory, HasUuids;

    protected $table = 'student_course';

    public $incrementing = false;

    protected $fillable = [
        'student_id', 'course_id', 'enrolled_at', 'expires_at',
        'completed_at', 'is_active', 'notes',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
