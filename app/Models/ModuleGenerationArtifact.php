<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Motore generazione corsi da KB — un artefatto AI (manuale discente, manuale
// formatore, slide) per modulo, con stato di revisione HITL indipendente dagli
// altri due. `artifact_id` referenzia debolmente ModulePresentation per le slide.
class ModuleGenerationArtifact extends Model
{
    use HasUuids;

    public const TYPE_STUDENT_MANUAL = 'student_manual';
    public const TYPE_INSTRUCTOR_MANUAL = 'instructor_manual';
    public const TYPE_SLIDES = 'slides';

    protected $fillable = [
        'run_id', 'module_id', 'artifact_type', 'status',
        'artifact_id', 'content', 'generation_meta',
        'reviewed_by', 'reviewed_at', 'edited_by_human',
    ];

    protected $casts = [
        'content' => 'array',
        'generation_meta' => 'array',
        'reviewed_at' => 'datetime',
        'edited_by_human' => 'boolean',
    ];

    public function run()
    {
        return $this->belongsTo(CourseGenerationRun::class, 'run_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /** La presentazione collegata, per gli artefatti di tipo slides. */
    public function presentation()
    {
        return $this->belongsTo(ModulePresentation::class, 'artifact_id');
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['pending_review', 'rejected'], true);
    }
}
