<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class BlogPost extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'cover_image_url',
        'tags',
        'category',
        'published',
        'published_at',
        'author_name',
        'author_id',
        'views_count',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'tags' => 'array',
        'published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)
                     ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
