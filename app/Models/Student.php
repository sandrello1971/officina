<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'company', 'role',
        'avatar_url', 'is_active', 'must_change_password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'student_course')
            ->withPivot('enrolled_at', 'expires_at', 'completed_at', 'is_active', 'notes')
            ->withTimestamps();
    }

    public function moduleProgress()
    {
        return $this->hasMany(StudentModuleProgress::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }
}
