<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * News AI — audit di un recupero settimanale. Gemella di FreshnessRun: append-only,
 * niente updated_at (gestiamo solo created_at + started/finished).
 */
class NewsRun extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'status',
        'started_at',
        'finished_at',
        'items_found',
        'failure_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'items_found' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NewsItem::class, 'run_id');
    }
}
