<?php

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class SecurityProfile extends Model
{
    use SoftDeletes;

    protected $table = 'security_profiles';

    protected $fillable = [
        'public_id',
        'name',
        'code',
        'settings',
        'is_default',
    ];

    protected $casts = [
        'settings' => 'json',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SecurityProfile $profile) {
            if (empty($profile->public_id)) {
                $profile->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
