<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id', 'teacher_id', 'name', 'subject_id', 'school_year',
        'invite_code', 'invite_enabled', 'requires_approval', 'is_archived',
    ];

    protected $casts = [
        'invite_enabled' => 'boolean',
        'requires_approval' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classStudents()
    {
        return $this->hasMany(ClassStudent::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_students', 'school_class_id', 'student_id')
            ->withPivot('status', 'approved_at')
            ->withTimestamps();
    }

    public function publications()
    {
        return $this->hasMany(ArtifactPublication::class);
    }
}
