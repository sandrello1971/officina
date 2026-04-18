<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quiz_id', 'student_id', 'started_at', 'completed_at',
        'score', 'passed', 'time_spent_seconds', 'attempt_number',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }
}
