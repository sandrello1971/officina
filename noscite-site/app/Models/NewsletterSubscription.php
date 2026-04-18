<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'active',
        'token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->token)) {
                $subscription->token = Str::uuid()->toString();
            }
        });
    }
}
