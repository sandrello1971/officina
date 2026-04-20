<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbDocument extends Model
{
    protected $fillable = [
        'file_stem', 'title', 'tipo_documento', 'lingua',
        'sommario', 'body_md', 'tags', 'argomenti', 'file_originale',
        'file_path', 'file_type', 'file_size',
        'data_catalogazione', 'last_synced_at',
        'data_documento', 'organizzazioni', 'sentiment', 'complessita',
        'persone', 'luoghi', 'parole_chiave',
    ];

    protected $casts = [
        'tags' => 'array',
        'argomenti' => 'array',
        'data_catalogazione' => 'date',
        'last_synced_at' => 'datetime',
        'data_documento' => 'date',
        'persone' => 'array',
        'luoghi' => 'array',
        'parole_chiave' => 'array',
    ];
}
