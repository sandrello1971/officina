<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Segnalazione del controllo di completezza della consegna (slide, materiali, sezione nel
 * manuale, HTML integro, ordinamento, permessi filesystem). Solo lettura sui corsi: qui non
 * si scrive mai un corso, si tiene traccia di cosa manca. `resolved_at` è impostato dal
 * comando quando il problema non è più rilevato al giro successivo; `dismissed_at` è
 * un'azione admin esplicita per "so che manca, non conta per ora".
 */
class CompletenessFinding extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id', 'module_id', 'check_type', 'severity', 'message',
        'detected_at', 'resolved_at', 'dismissed_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at')->whereNull('dismissed_at');
    }
}
