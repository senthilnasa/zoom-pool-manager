<?php

namespace App\Domain\HostControl\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class Override extends Model
{
    protected $table = 'overrides';

    protected $fillable = [
        'public_id',
        'actor_user_id',
        'target_type',
        'target_id',
        'field',
        'old_value',
        'new_value',
        'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Override $override) {
            if (empty($override->public_id)) {
                $override->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
