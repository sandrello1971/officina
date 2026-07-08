<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tag corso — TRASVERSALE: un corso può avere N tag. Tabella gestita,
 * tag creati per-deployment via admin.
 */
class CourseTag extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug'];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_course_tag');
    }
}
