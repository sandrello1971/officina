<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DocumentRag extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'documents_rag';

    protected $fillable = [
        'course_id', 'module_id', 'title', 'content',
        'file_path', 'chunk_index', 'metadata', 'is_instructor_only',
        'school_class_id', 'teacher_id', 'scope', 'subject_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_instructor_only' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // ===== Schola =====
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Student::class, 'teacher_id');
    }

    /**
     * Ricompone il testo di un gruppo di chunk (stesso title/course, ordinati
     * per chunk_index) in un unico testo continuo, senza duplicare l'overlap
     * di 200 char introdotto da RagService::chunkText(): primo chunk intero,
     * successivi troncati dell'overlap.
     */
    public static function reassembleGroup(Collection $chunksOrderedByIndex): string
    {
        $out = '';
        foreach ($chunksOrderedByIndex->values() as $i => $chunk) {
            $out .= $i === 0 ? $chunk->content : mb_substr((string) $chunk->content, 200);
        }

        return trim($out);
    }
}
