<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria corso — ESCLUSIVA: un corso appartiene al più a una categoria.
 * Tabella gestita (non enum): categorie create per-deployment via admin.
 */
class CourseCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug', 'color', 'sort_order'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
