<?php

namespace App\Domain\Users\Models;

use App\Domain\Auth\Models\MfaRecoveryCode;
use App\Domain\Auth\Models\MfaSecret;
use App\Domain\Auth\Models\UserIdentity;
use App\Domain\Auth\Traits\HasDepartmentScope;
use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\MeetingApproval;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $department_id
 * @property string $name
 * @property string $email
 * @property string|null $designation
 * @property string|null $password
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $theme
 * @property bool $is_active
 * @property bool $mfa_enabled
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 */
class User extends Authenticatable
{
    use HasDepartmentScope, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'public_id',
        'department_id',
        'name',
        'email',
        'designation',
        'password',
        'timezone',
        'locale',
        'theme',
        'is_active',
        'mfa_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mfa_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->public_id)) {
                $user->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<MfaSecret, $this>
     */
    public function mfaSecret(): HasOne
    {
        return $this->hasOne(MfaSecret::class);
    }

    /**
     * @return HasMany<MfaRecoveryCode, $this>
     */
    public function mfaRecoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    /**
     * @return HasMany<UserIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /**
     * @return HasMany<MeetingApproval, $this>
     */
    public function meetingApprovals(): HasMany
    {
        return $this->hasMany(MeetingApproval::class, 'approver_user_id');
    }

    /**
     * @return HasMany<ApprovalDelegation, $this>
     */
    public function approvalDelegations(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'user_id');
    }

    /**
     * @return HasMany<ApprovalDelegation, $this>
     */
    public function delegatedToMe(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'delegate_user_id');
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'user_id');
    }

    /**
     * @return HasMany<CloudRecording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(CloudRecording::class, 'logical_owner_user_id');
    }
}
