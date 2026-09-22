<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'login_attempts';

    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'was_successful',
        'failure_reason',
        'created_at',
    ];

    protected $casts = [
        'was_successful' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LoginAttempt $attempt) {
            $attempt->created_at = $attempt->created_at ?? now();
        });
    }
}
