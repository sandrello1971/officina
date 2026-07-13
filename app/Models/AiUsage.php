<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use HasUuids;

    protected $table = 'ai_usage';
    public $timestamps = false; // solo created_at, impostato dal client

    protected $fillable = [
        'feature', 'model', 'tokens_in', 'tokens_out', 'cost_usd', 'status', 'error',
        'school_id', 'course_id', 'actor_type', 'actor_id', 'meta', 'created_at',
    ];

    protected $casts = [
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cost_usd' => 'decimal:6',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];
}
