<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Auth\Models\DirectorySyncConfig;
use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Providers\SamlIdentityProvider;
use App\Domain\Auth\Services\DirectorySyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

class SpaIdentityController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected SamlIdentityProvider $samlProvider
    ) {}

    // ==========================================
    // 1. SSO & SAML IDENTITY PROVIDERS
    // ==========================================

    /**
     * List all SSO / SAML Identity Providers.
     */
    public function identityProviders(): JsonResponse
    {
        $providers = IdentityProvider::withCount('userIdentities')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (IdentityProvider $idp) {
                return [
                    'id' => $idp->id,
                    'public_id' => $idp->public_id,
                    'name' => $idp->name,
                    'driver' => $idp->driver,
                    'client_id' => $idp->client_id,
                    'has_client_secret' => ! empty($idp->client_secret),
                    'tenant_id' => $idp->tenant_id,
                    'metadata_url' => $idp->metadata_url,
                    'has_primary_cert' => ! empty($idp->certificate_primary),
                    'has_secondary_cert' => ! empty($idp->certificate_secondary),
                    'allowed_domains' => $idp->allowed_domains ?? [],
                    'role_mapping' => $idp->role_mapping ?? [],
                    'department_mapping' => $idp->department_mapping ?? [],
                    'enabled' => (bool) $idp->enabled,
                    'users_count' => $idp->user_identities_count,
                    'acs_url' => route('auth.saml.acs', ['provider' => $idp->public_id]),
                    'sp_entity_id' => route('auth.saml.metadata', ['provider' => $idp->public_id]),
                    'login_redirect_url' => route('auth.sso.redirect', ['provider' => $idp->public_id]),
                    'created_at' => $idp->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'providers' => $providers,
        ]);
    }

    /**
     * Create a new Identity Provider.
     */
    public function storeIdentityProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'driver' => 'required|string|in:google,microsoft,azure,saml',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string',
            'tenant_id' => 'nullable|string|max:255',
            'metadata_url' => 'nullable|url|max:500',
            'certificate_primary' => 'nullable|string',
            'certificate_secondary' => 'nullable|string',
            'allowed_domains' => 'nullable|array',
            'role_mapping' => 'nullable|array',
            'department_mapping' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        $idp = IdentityProvider::create([
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'client_id' => $validated['client_id'] ?? null,
            'client_secret' => $validated['client_secret'] ?? null,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'metadata_url' => $validated['metadata_url'] ?? null,
            'certificate_primary' => $validated['certificate_primary'] ?? null,
            'certificate_secondary' => $validated['certificate_secondary'] ?? null,
            'allowed_domains' => $validated['allowed_domains'] ?? [],
            'role_mapping' => $validated['role_mapping'] ?? [],
            'department_mapping' => $validated['department_mapping'] ?? [],
            'enabled' => ! empty($validated['enabled']),
        ]);

        $this->auditService->log(
            event: 'identity_provider.created',
            auditable: $idp,
            oldValues: [],
            newValues: ['name' => $idp->name, 'driver' => $idp->driver]
        );

        return response()->json([
            'success' => true,
            'message' => "Identity provider '{$idp->name}' registered successfully.",
            'provider' => [
                'public_id' => $idp->public_id,
                'name' => $idp->name,
                'driver' => $idp->driver,
                'enabled' => $idp->enabled,
            ],
        ], 201);
    }

    /**
     * Show Identity Provider details.
     */
    public function showIdentityProvider(string $publicId): JsonResponse
    {
        $idp = IdentityProvider::where('public_id', $publicId)->firstOrFail();

        return response()->json([
            'provider' => [
                'id' => $idp->id,
                'public_id' => $idp->public_id,
                'name' => $idp->name,
                'driver' => $idp->driver,
                'client_id' => $idp->client_id,
                'has_client_secret' => ! empty($idp->client_secret),
                'tenant_id' => $idp->tenant_id,
                'metadata_url' => $idp->metadata_url,
                'certificate_primary' => $idp->certificate_primary,
                'certificate_secondary' => $idp->certificate_secondary,
                'allowed_domains' => $idp->allowed_domains ?? [],
                'role_mapping' => $idp->role_mapping ?? [],
                'department_mapping' => $idp->department_mapping ?? [],
                'enabled' => (bool) $idp->enabled,
                'acs_url' => route('auth.saml.acs', ['provider' => $idp->public_id]),
                'sp_entity_id' => route('auth.saml.metadata', ['provider' => $idp->public_id]),
            ],
        ]);
    }

    /**
     * Update Identity Provider.
     */
    public function updateIdentityProvider(Request $request, string $publicId): JsonResponse
    {
        $idp = IdentityProvider::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'driver' => 'sometimes|required|string|in:google,microsoft,azure,saml',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string',
            'tenant_id' => 'nullable|string|max:255',
            'metadata_url' => 'nullable|url|max:500',
            'certificate_primary' => 'nullable|string',
            'certificate_secondary' => 'nullable|string',
            'allowed_domains' => 'nullable|array',
            'role_mapping' => 'nullable|array',
            'department_mapping' => 'nullable|array',
            'enabled' => 'nullable|boolean',
        ]);

        $updateData = [];
        foreach (['name', 'driver', 'client_id', 'tenant_id', 'metadata_url', 'certificate_primary', 'certificate_secondary', 'allowed_domains', 'role_mapping', 'department_mapping'] as $f) {
            if ($request->has($f)) {
                $updateData[$f] = $validated[$f];
            }
        }

        if ($request->filled('client_secret')) {
            $updateData['client_secret'] = $validated['client_secret'];
        }

        if ($request->has('enabled')) {
            $updateData['enabled'] = (bool) $validated['enabled'];
        }

        $idp->update($updateData);

        $this->auditService->log(
            event: 'identity_provider.updated',
            auditable: $idp,
            oldValues: [],
            newValues: ['name' => $idp->name, 'enabled' => $idp->enabled]
        );

        return response()->json([
            'success' => true,
            'message' => "Identity provider '{$idp->name}' updated successfully.",
        ]);
    }

    /**
     * Delete an Identity Provider.
     */
    public function deleteIdentityProvider(string $publicId): JsonResponse
    {
        $idp = IdentityProvider::where('public_id', $publicId)->firstOrFail();
        $name = $idp->name;
        $id = $idp->id;

        $idp->delete();

        $this->auditService->log(
            event: 'identity_provider.deleted',
            auditable: null,
            oldValues: ['name' => $name, 'id' => $id],
            newValues: []
        );

        return response()->json([
            'success' => true,
            'message' => "Identity provider '{$name}' deleted.",
        ]);
    }

    /**
     * Preview or download Service Provider (SP) Metadata XML for SAML.
     */
    public function spMetadata(string $publicId): Response
    {
        $idp = IdentityProvider::where('public_id', $publicId)->firstOrFail();
        $xml = $this->samlProvider->generateSpMetadata($idp);

        return response($xml, 200, [
            'Content-Type' => 'application/samlmetadata+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="sp-metadata-'.$idp->public_id.'.xml"',
        ]);
    }

    /**
     * Test Identity Provider configuration.
     */
    public function testIdentityProvider(string $publicId): JsonResponse
    {
        $idp = IdentityProvider::where('public_id', $publicId)->firstOrFail();

        $diagnostics = [];
        $isValid = true;

        if ($idp->driver === 'saml') {
            if (empty($idp->certificate_primary) && empty($idp->certificate_secondary)) {
                $diagnostics[] = 'Warning: No IdP verification certificate configured.';
                $isValid = false;
            } else {
                $diagnostics[] = '✓ Verification certificate is installed.';
            }

            if (empty($idp->metadata_url)) {
                $diagnostics[] = 'Notice: IdP SSO Service URL is not set.';
            } else {
                $diagnostics[] = '✓ IdP SSO Service URL configured: '.$idp->metadata_url;
            }
        } elseif ($idp->driver === 'google') {
            if (empty($idp->client_id) || empty($idp->client_secret)) {
                $diagnostics[] = 'Warning: Google OAuth Client ID or Client Secret is missing.';
                $isValid = false;
            } else {
                $diagnostics[] = '✓ Google OAuth credentials configured.';
            }
        } elseif (in_array($idp->driver, ['microsoft', 'azure'], true)) {
            if (empty($idp->client_id) || empty($idp->client_secret)) {
                $diagnostics[] = 'Warning: Microsoft Entra ID Client ID or Client Secret is missing.';
                $isValid = false;
            } else {
                $diagnostics[] = '✓ Microsoft Entra ID credentials configured (Tenant: '.($idp->tenant_id ?: 'common').').';
            }
        }

        return response()->json([
            'success' => $isValid,
            'diagnostics' => $diagnostics,
            'message' => $isValid ? 'Identity provider configuration is valid.' : 'Configuration requires attention.',
        ]);
    }

    // ==========================================
    // 2. DIRECTORY & AD SYNCHRONIZATION
    // ==========================================

    /**
     * List all Directory / AD Sync configurations.
     */
    public function directorySyncConfigs(): JsonResponse
    {
        $configs = DirectorySyncConfig::with('defaultDepartment')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (DirectorySyncConfig $cfg) {
                return [
                    'id' => $cfg->id,
                    'public_id' => $cfg->public_id,
                    'name' => $cfg->name,
                    'provider_type' => $cfg->provider_type,
                    'is_active' => (bool) $cfg->is_active,
                    'sync_interval_minutes' => $cfg->sync_interval_minutes,
                    'tenant_id' => $cfg->tenant_id,
                    'client_id' => $cfg->client_id,
                    'has_client_secret' => ! empty($cfg->client_secret),
                    'admin_email' => $cfg->admin_email,
                    'has_service_account' => ! empty($cfg->service_account_json),
                    'ldap_host' => $cfg->ldap_host,
                    'ldap_port' => $cfg->ldap_port,
                    'ldap_base_dn' => $cfg->ldap_base_dn,
                    'ldap_bind_dn' => $cfg->ldap_bind_dn,
                    'has_ldap_password' => ! empty($cfg->ldap_bind_password),
                    'ldap_use_ssl' => (bool) $cfg->ldap_use_ssl,
                    'domain_filter' => $cfg->domain_filter,
                    'group_filter' => $cfg->group_filter,
                    'default_role' => $cfg->default_role,
                    'default_department_id' => $cfg->default_department_id,
                    'default_department_name' => $cfg->defaultDepartment?->name,
                    'auto_create_departments' => (bool) $cfg->auto_create_departments,
                    'deactivate_missing_users' => (bool) $cfg->deactivate_missing_users,
                    'last_synced_at' => $cfg->last_synced_at?->toIso8601String(),
                    'last_sync_status' => $cfg->last_sync_status,
                    'last_sync_message' => $cfg->last_sync_message,
                    'last_sync_stats' => $cfg->last_sync_stats ?? [
                        'total_scanned' => 0,
                        'created' => 0,
                        'updated' => 0,
                        'deactivated' => 0,
                    ],
                ];
            });

        return response()->json([
            'configs' => $configs,
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name')->values()->toArray(),
        ]);
    }

    /**
     * Create a new Directory Sync configuration.
     */
    public function storeDirectorySyncConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'provider_type' => 'required|string|in:microsoft_entra,google_workspace,ldap_active_directory',
            'is_active' => 'nullable|boolean',
            'sync_interval_minutes' => 'nullable|integer|min:5|max:1440',
            'tenant_id' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string',
            'service_account_json' => 'nullable|string',
            'admin_email' => 'nullable|email|max:190',
            'ldap_host' => 'nullable|string|max:255',
            'ldap_port' => 'nullable|integer',
            'ldap_base_dn' => 'nullable|string|max:255',
            'ldap_bind_dn' => 'nullable|string|max:255',
            'ldap_bind_password' => 'nullable|string',
            'ldap_use_ssl' => 'nullable|boolean',
            'domain_filter' => 'nullable|string|max:255',
            'group_filter' => 'nullable|string|max:255',
            'default_role' => 'nullable|string|max:50',
            'default_department_id' => 'nullable|exists:departments,id',
            'auto_create_departments' => 'nullable|boolean',
            'deactivate_missing_users' => 'nullable|boolean',
        ]);

        $config = DirectorySyncConfig::create([
            'name' => $validated['name'],
            'provider_type' => $validated['provider_type'],
            'is_active' => $validated['is_active'] ?? true,
            'sync_interval_minutes' => $validated['sync_interval_minutes'] ?? 60,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'client_id' => $validated['client_id'] ?? null,
            'client_secret' => $validated['client_secret'] ?? null,
            'service_account_json' => $validated['service_account_json'] ?? null,
            'admin_email' => $validated['admin_email'] ?? null,
            'ldap_host' => $validated['ldap_host'] ?? null,
            'ldap_port' => $validated['ldap_port'] ?? 389,
            'ldap_base_dn' => $validated['ldap_base_dn'] ?? null,
            'ldap_bind_dn' => $validated['ldap_bind_dn'] ?? null,
            'ldap_bind_password' => $validated['ldap_bind_password'] ?? null,
            'ldap_use_ssl' => ! empty($validated['ldap_use_ssl']),
            'domain_filter' => $validated['domain_filter'] ?? null,
            'group_filter' => $validated['group_filter'] ?? null,
            'default_role' => $validated['default_role'] ?? 'Standard User',
            'default_department_id' => $validated['default_department_id'] ?? null,
            'auto_create_departments' => $validated['auto_create_departments'] ?? true,
            'deactivate_missing_users' => ! empty($validated['deactivate_missing_users']),
        ]);

        $this->auditService->log(
            event: 'directory_sync.created',
            auditable: $config,
            oldValues: [],
            newValues: ['name' => $config->name, 'provider_type' => $config->provider_type]
        );

        return response()->json([
            'success' => true,
            'message' => "Directory connector '{$config->name}' created successfully.",
            'config' => [
                'public_id' => $config->public_id,
                'name' => $config->name,
                'provider_type' => $config->provider_type,
            ],
        ], 201);
    }

    /**
     * Show Directory Sync configuration.
     */
    public function showDirectorySyncConfig(string $publicId): JsonResponse
    {
        $cfg = DirectorySyncConfig::where('public_id', $publicId)->firstOrFail();

        return response()->json([
            'config' => [
                'id' => $cfg->id,
                'public_id' => $cfg->public_id,
                'name' => $cfg->name,
                'provider_type' => $cfg->provider_type,
                'is_active' => (bool) $cfg->is_active,
                'sync_interval_minutes' => $cfg->sync_interval_minutes,
                'tenant_id' => $cfg->tenant_id,
                'client_id' => $cfg->client_id,
                'has_client_secret' => ! empty($cfg->client_secret),
                'admin_email' => $cfg->admin_email,
                'has_service_account' => ! empty($cfg->service_account_json),
                'ldap_host' => $cfg->ldap_host,
                'ldap_port' => $cfg->ldap_port,
                'ldap_base_dn' => $cfg->ldap_base_dn,
                'ldap_bind_dn' => $cfg->ldap_bind_dn,
                'has_ldap_password' => ! empty($cfg->ldap_bind_password),
                'ldap_use_ssl' => (bool) $cfg->ldap_use_ssl,
                'domain_filter' => $cfg->domain_filter,
                'group_filter' => $cfg->group_filter,
                'default_role' => $cfg->default_role,
                'default_department_id' => $cfg->default_department_id,
                'auto_create_departments' => (bool) $cfg->auto_create_departments,
                'deactivate_missing_users' => (bool) $cfg->deactivate_missing_users,
                'last_synced_at' => $cfg->last_synced_at?->toIso8601String(),
                'last_sync_status' => $cfg->last_sync_status,
                'last_sync_message' => $cfg->last_sync_message,
                'last_sync_stats' => $cfg->last_sync_stats,
            ],
        ]);
    }

    /**
     * Update Directory Sync configuration.
     */
    public function updateDirectorySyncConfig(Request $request, string $publicId): JsonResponse
    {
        $cfg = DirectorySyncConfig::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'provider_type' => 'sometimes|required|string|in:microsoft_entra,google_workspace,ldap_active_directory',
            'is_active' => 'nullable|boolean',
            'sync_interval_minutes' => 'nullable|integer|min:5|max:1440',
            'tenant_id' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string',
            'service_account_json' => 'nullable|string',
            'admin_email' => 'nullable|email|max:190',
            'ldap_host' => 'nullable|string|max:255',
            'ldap_port' => 'nullable|integer',
            'ldap_base_dn' => 'nullable|string|max:255',
            'ldap_bind_dn' => 'nullable|string|max:255',
            'ldap_bind_password' => 'nullable|string',
            'ldap_use_ssl' => 'nullable|boolean',
            'domain_filter' => 'nullable|string|max:255',
            'group_filter' => 'nullable|string|max:255',
            'default_role' => 'nullable|string|max:50',
            'default_department_id' => 'nullable|exists:departments,id',
            'auto_create_departments' => 'nullable|boolean',
            'deactivate_missing_users' => 'nullable|boolean',
        ]);

        $updateData = [];
        $fields = [
            'name', 'provider_type', 'is_active', 'sync_interval_minutes',
            'tenant_id', 'client_id', 'admin_email',
            'ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_bind_dn', 'ldap_use_ssl',
            'domain_filter', 'group_filter', 'default_role', 'default_department_id',
            'auto_create_departments', 'deactivate_missing_users',
        ];

        foreach ($fields as $f) {
            if ($request->has($f)) {
                $updateData[$f] = $validated[$f];
            }
        }

        if ($request->filled('client_secret')) {
            $updateData['client_secret'] = $validated['client_secret'];
        }
        if ($request->filled('service_account_json')) {
            $updateData['service_account_json'] = $validated['service_account_json'];
        }
        if ($request->filled('ldap_bind_password')) {
            $updateData['ldap_bind_password'] = $validated['ldap_bind_password'];
        }

        $cfg->update($updateData);

        $this->auditService->log(
            event: 'directory_sync.updated',
            auditable: $cfg,
            oldValues: [],
            newValues: ['name' => $cfg->name]
        );

        return response()->json([
            'success' => true,
            'message' => "Directory connector '{$cfg->name}' updated successfully.",
        ]);
    }

    /**
     * Delete Directory Sync configuration.
     */
    public function deleteDirectorySyncConfig(string $publicId): JsonResponse
    {
        $cfg = DirectorySyncConfig::where('public_id', $publicId)->firstOrFail();
        $name = $cfg->name;
        $id = $cfg->id;

        $cfg->delete();

        $this->auditService->log(
            event: 'directory_sync.deleted',
            auditable: null,
            oldValues: ['name' => $name, 'id' => $id],
            newValues: []
        );

        return response()->json([
            'success' => true,
            'message' => "Directory connector '{$name}' deleted.",
        ]);
    }

    /**
     * Trigger immediate directory synchronization.
     */
    public function syncDirectoryNow(string $publicId, DirectorySyncService $syncService): JsonResponse
    {
        $cfg = DirectorySyncConfig::where('public_id', $publicId)->firstOrFail();

        $result = $syncService->sync($cfg, dryRun: false);

        return response()->json($result);
    }

    /**
     * Test directory connection.
     */
    public function testDirectoryConnection(string $publicId, DirectorySyncService $syncService): JsonResponse
    {
        $cfg = DirectorySyncConfig::where('public_id', $publicId)->firstOrFail();

        $result = $syncService->testConnection($cfg);

        return response()->json($result);
    }
}
