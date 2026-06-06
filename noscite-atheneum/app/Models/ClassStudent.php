<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassStudent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'class_students';

    protected $fillable = [
        'school_class_id', 'student_id', 'status', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
