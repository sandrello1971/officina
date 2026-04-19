<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbDocument extends Model
{
    protected $fillable = [
        'file_stem', 'title', 'tipo_documento', 'lingua',
        'sommario', 'tags', 'argomenti', 'file_originale',
        'file_path', 'file_type', 'file_size',
        'data_catalogazione', 'last_synced_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'argomenti' => 'array',
        'data_catalogazione' => 'date',
        'last_synced_at' => 'datetime',
    ];
}
