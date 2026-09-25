<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasUuids;

    // Stato dell'appello del formatore (source = instructor_mark).
    public const STATUS_PRESENT = 'presente';
    public const STATUS_ABSENT = 'assente';
    public const STATUS_JUSTIFIED = 'assente_giustificato';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Presente',
        self::STATUS_ABSENT => 'Assente',
        self::STATUS_JUSTIFIED => 'Assente giustificato',
    ];

    protected $fillable = [
        'student_id', 'course_id', 'type', 'source', 'status', 'arrived_at', 'left_at', 'note', 'marked_by',
        'course_session_id', 'module_id', 'occurred_at', 'hours_credited', 'ip', 'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'hours_credited' => 'decimal:2',
        'meta' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function isPresent(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }

    public function session()
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }
}
