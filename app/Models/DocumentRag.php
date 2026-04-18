<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRag extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'documents_rag';

    protected $fillable = [
        'course_id', 'module_id', 'title', 'content',
        'file_path', 'chunk_index', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
