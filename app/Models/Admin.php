<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admin extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
        'password'  => 'hashed',
    ];

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }
}
