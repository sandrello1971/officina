<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CourseSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'course_id', 'course_edition_id', 'day_number', 'title', 'scheduled_at', 'duration_minutes', 'modality', 'location', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function edition()
    {
        return $this->belongsTo(CourseEdition::class, 'course_edition_id');
    }

    /** Fine prevista della giornata. */
    public function endsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->scheduled_at?->copy()->addMinutes((int) $this->duration_minutes);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
