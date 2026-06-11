<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description', 'color',
        'icon', 'duration_hours', 'certification_name', 'is_active', 'sort_order',
        'video_ai_id', 'video_filename', 'video_status',
        'exam_prep_html',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function instructorMaterials()
    {
        return $this->hasMany(Material::class)
            ->where('is_instructor_only', true)
            ->orderBy('sort_order');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function documentsRag()
    {
        return $this->hasMany(DocumentRag::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_course')
            ->withPivot('enrolled_at', 'expires_at', 'completed_at', 'is_active', 'notes', 'instructor_id')
            ->withTimestamps();
    }

    public function instructors()
    {
        return $this->belongsToMany(Student::class, 'course_instructor', 'course_id', 'instructor_id')
            ->withTimestamps();
    }

    public function hasMultipleInstructors(): bool
    {
        return $this->instructors()->count() > 1;
    }

    public function conceptMaps()
    {
        return $this->hasMany(CourseConceptMap::class)->orderBy('sort_order')->orderBy('created_at');
    }

    // P25.1 — sorgenti strutturati versionati (Course Freshness Agent).
    public function sources()
    {
        return $this->hasMany(CourseSource::class)->orderByDesc('created_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
