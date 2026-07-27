<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * News AI — singola notizia. HITL: 'draft' → 'published' (visibile ai discenti) | 'rejected'.
 * `tags` classifica per argomento (generati dall'AI). Le fonti sono riportate dal modello.
 */
class NewsItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'run_id',
        'title',
        'summary',
        'body',
        'source_url',
        'source_name',
        'source_published_at',
        'tags',
        'confidence',
        'status',
        'reviewed_by',
        'reviewed_at',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'source_published_at' => 'date',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NewsRun::class, 'run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', 'draft');
    }
}
