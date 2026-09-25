<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Edizione di un corso: un ciclo di erogazione con le sue giornate
 * (course_sessions), i suoi discenti e il suo registro presenze.
 */
class CourseEdition extends Model
{
    use HasUuids;

    public const MODALITIES = [
        'in_person' => 'In aula',
        'live_online' => 'Live online',
        'blended' => 'Mista',
    ];

    protected $fillable = ['course_id', 'name', 'location', 'modality', 'instructor_id', 'notes'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Student::class, 'instructor_id');
    }

    /** Giornate in ordine di calendario. */
    public function days()
    {
        return $this->hasMany(CourseSession::class)->orderBy('scheduled_at');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'course_edition_students')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function modalityLabel(): string
    {
        return self::MODALITIES[$this->modality] ?? $this->modality;
    }

    public function plannedHours(): float
    {
        return round($this->days->sum('duration_minutes') / 60, 2);
    }
}
