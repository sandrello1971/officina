<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false; // solo created_at, impostato dal middleware

    protected $fillable = [
        'area', 'actor_type', 'actor_id', 'actor_label',
        'action', 'method', 'path', 'status',
        'subject_type', 'subject_id', 'ip', 'user_agent', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'status' => 'integer',
        'created_at' => 'datetime',
    ];
}
