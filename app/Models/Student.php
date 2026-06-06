<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    public const SYSTEM_ROLES = [
        'student'    => 'Studente',
        'instructor' => 'Formatore',
        'admin'      => 'Amministratore',
        'professor'  => 'Docente (Schola)',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'company', 'job_title', 'role',
        'avatar_url', 'is_active', 'is_demo', 'must_change_password',
        'microsoft_id', 'auto_enroll_all_courses', 'birth_date',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'must_change_password' => 'boolean',
        'auto_enroll_all_courses' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'birth_date' => 'date',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'student_course')
            ->withPivot('enrolled_at', 'expires_at', 'completed_at', 'is_active', 'notes', 'instructor_id')
            ->withTimestamps();
    }

    public function taughtCourses()
    {
        return $this->belongsToMany(Course::class, 'course_instructor', 'instructor_id', 'course_id')
            ->withTimestamps();
    }

    public function mentoredStudents()
    {
        return $this->hasMany(StudentCourse::class, 'instructor_id');
    }

    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Docente Schola — distinto da instructor (formatore corsi Atheneum).
    public function isProfessor(): bool
    {
        return $this->role === 'professor';
    }

    public function moduleProgress()
    {
        return $this->hasMany(StudentModuleProgress::class);
    }

    public function conversationsAsStudent()
    {
        return $this->hasMany(Conversation::class, 'student_id');
    }

    public function conversationsAsInstructor()
    {
        return $this->hasMany(Conversation::class, 'instructor_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function instructorNotes()
    {
        return $this->hasMany(InstructorNote::class, 'instructor_id');
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function conceptMapForks()
    {
        return $this->hasMany(StudentConceptMap::class);
    }

    // ===== Schola =====

    // Come docente (role=professor)
    public function schoolClassesAsTeacher()
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    public function teachingDocuments()
    {
        return $this->hasMany(TeachingDocument::class, 'teacher_id');
    }

    public function teachingArtifacts()
    {
        return $this->hasMany(TeachingArtifact::class, 'teacher_id');
    }

    // Come studente di classe
    public function classEnrollments()
    {
        return $this->hasMany(ClassStudent::class, 'student_id');
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_students', 'student_id', 'school_class_id')
            ->withPivot('status', 'approved_at')
            ->withTimestamps();
    }
}
