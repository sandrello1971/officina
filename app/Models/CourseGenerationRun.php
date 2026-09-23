<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Motore generazione corsi da KB — un run per lancio del motore su un corso.
// `brief` guida sia l'outline sia i contenuti dei moduli; `outline` è la proposta
// di struttura (array di capitoli) in attesa di revisione formatore.
class CourseGenerationRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'course_id', 'status', 'phase', 'brief', 'outline',
        'triggered_by', 'started_at', 'completed_at', 'error',
    ];

    protected $casts = [
        'brief' => 'array',
        'outline' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function artifacts()
    {
        return $this->hasMany(ModuleGenerationArtifact::class, 'run_id');
    }

    public function triggeredBy()
    {
        return $this->belongsTo(Admin::class, 'triggered_by');
    }

    public function isOutlinePending(): bool
    {
        return $this->phase === 'outline' && $this->status !== 'failed';
    }
}
