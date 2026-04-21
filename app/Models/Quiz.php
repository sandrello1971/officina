<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'course_id', 'title', 'description',
        'passing_score', 'time_limit_minutes', 'max_attempts',
        'randomize_questions', 'show_results_immediately', 'is_active',
        'is_demo',
    ];

    protected $casts = [
        'randomize_questions' => 'boolean',
        'show_results_immediately' => 'boolean',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
