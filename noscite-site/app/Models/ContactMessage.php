<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ContactMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'message',
        'privacy_accepted',
        'status',
        'ip_address',
    ];

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }
}
