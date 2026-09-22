<?php

namespace App\Domain\Auth\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentity extends Model
{
    protected $table = 'user_identities';

    protected $fillable = [
        'user_id',
        'identity_provider_id',
        'external_id',
        'email',
        'last_authenticated_at',
    ];

    protected $casts = [
        'last_authenticated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<IdentityProvider, $this>
     */
    public function identityProvider(): BelongsTo
    {
        return $this->belongsTo(IdentityProvider::class);
    }
}
