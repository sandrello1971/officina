<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntranetTool extends Model
{
    protected $fillable = [
        'section', 'type', 'icon', 'name', 'description',
        'url', 'label', 'credentials', 'status', 'active', 'sort_order',
        'server_id',
    ];

    protected $casts = ['active' => 'boolean'];

    public function server()
    {
        return $this->belongsTo(IntranetServer::class, 'server_id');
    }
}
