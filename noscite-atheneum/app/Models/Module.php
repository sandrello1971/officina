<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id', 'title', 'description', 'content',
        'duration_minutes', 'sort_order', 'is_active', 'video_url',
        'video_ai_id', 'video_filename', 'video_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class)->orderBy('sort_order');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function documentsRag()
    {
        return $this->hasMany(DocumentRag::class);
    }

    public function instructorManualSections()
    {
        return $this->hasMany(InstructorManualSection::class, 'module_id')
            ->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
