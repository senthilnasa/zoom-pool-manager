<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\Ulid;

class IdentityProvider extends Model
{
    protected $table = 'identity_providers';

    protected $fillable = [
        'public_id',
        'name',
        'driver',
        'client_id',
        'client_secret',
        'tenant_id',
        'metadata_url',
        'metadata_xml',
        'certificate_primary',
        'certificate_secondary',
        'allowed_domains',
        'role_mapping',
        'department_mapping',
        'enabled',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'allowed_domains' => 'json',
        'role_mapping' => 'json',
        'department_mapping' => 'json',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (IdentityProvider $provider) {
            if (empty($provider->public_id)) {
                $provider->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return HasMany<UserIdentity, $this>
     */
    public function userIdentities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }
}
