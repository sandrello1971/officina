<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntranetServer extends Model
{
    protected $fillable = [
        'name', 'hostname', 'ip_address', 'url', 'provider',
        'github_url', 'service', 'status', 'os', 'specs', 'notes', 'sort_order',
    ];
}
