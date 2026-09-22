<?php

namespace App\Domain\Auth\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaSecret extends Model
{
    protected $table = 'mfa_secrets';

    protected $fillable = [
        'user_id',
        'secret',
        'enrolled_at',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'enrolled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MfaSecret $secret) {
            $secret->enrolled_at = $secret->enrolled_at ?? now();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
