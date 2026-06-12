<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// P25.3 — Proposta di aggiornamento nella coda HITL. Nasce da un freshness_claim
// 'obsoleto'. `before` (verbatim) è l'ancora per l'applicazione; nulla viene applicato
// senza status='approved' (HITL non negoziabile).
class UpdateProposal extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'run_id',
        'freshness_claim_id',
        'course_id',
        'block_id',
        'sentence_ref',
        'before',
        'after',
        'reason',
        'source',
        'source_type',
        'confidence',
        'audience',
        'status',
        'after_edited_by_human',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
    ];

    protected $casts = [
        'sentence_ref' => 'integer',
        'confidence' => 'float',
        'after_edited_by_human' => 'boolean',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(FreshnessClaim::class, 'freshness_claim_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FreshnessRun::class, 'run_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
