<?php

namespace App\Domain\Auth\Models;

use App\Domain\Users\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class DirectorySyncConfig extends Model
{
    protected $table = 'directory_sync_configs';

    protected $fillable = [
        'public_id',
        'name',
        'provider_type',
        'is_active',
        'sync_interval_minutes',
        'tenant_id',
        'client_id',
        'client_secret',
        'service_account_json',
        'admin_email',
        'ldap_host',
        'ldap_port',
        'ldap_base_dn',
        'ldap_bind_dn',
        'ldap_bind_password',
        'ldap_use_ssl',
        'domain_filter',
        'group_filter',
        'default_role',
        'default_department_id',
        'auto_create_departments',
        'deactivate_missing_users',
        'last_synced_at',
        'last_sync_status',
        'last_sync_message',
        'last_sync_stats',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'client_secret' => 'encrypted',
        'service_account_json' => 'encrypted',
        'ldap_bind_password' => 'encrypted',
        'ldap_use_ssl' => 'boolean',
        'auto_create_departments' => 'boolean',
        'deactivate_missing_users' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_sync_stats' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (DirectorySyncConfig $config) {
            if (empty($config->public_id)) {
                $config->public_id = (string) new Ulid;
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
    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }
}
