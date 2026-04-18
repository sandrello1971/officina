<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'module_id', 'title', 'description', 'file_path', 'file_type',
        'file_size', 'external_url', 'sort_order', 'is_downloadable',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
