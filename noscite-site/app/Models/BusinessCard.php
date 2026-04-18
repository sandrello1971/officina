<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCard extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'username',
        'user_id',
        'full_name',
        'email',
        'phone',
        'company',
        'role',
        'website',
        'bio',
        'avatar_url',
        'social_links',
        'is_active',
        'views_count',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
