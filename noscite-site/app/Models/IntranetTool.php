<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntranetTool extends Model
{
    protected $fillable = [
        'section', 'type', 'icon', 'name', 'description',
        'url', 'label', 'credentials', 'status', 'active', 'sort_order',
    ];

    protected $casts = ['active' => 'boolean'];
}
