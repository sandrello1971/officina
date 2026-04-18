<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'is_public',
        'uploaded_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
