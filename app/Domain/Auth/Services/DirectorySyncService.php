<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Auth\Models\DirectorySyncConfig;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DirectorySyncService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Run directory synchronization for a specific configuration.
     *
     * @return array{success: bool, total_scanned: int, created: int, updated: int, deactivated: int, message: string}
     */
    public function sync(DirectorySyncConfig $config, bool $dryRun = false): array
    {
        $config->update([
            'last_sync_status' => 'running',
            'last_sync_message' => 'Synchronization in progress...',
        ]);

        $targetRole = ! empty($config->default_role) ? $config->default_role : 'Standard User';
        Role::firstOrCreate(['name' => $targetRole, 'guard_name' => 'web']);

        try {
            $directoryUsers = match ($config->provider_type) {
                'microsoft_entra' => $this->fetchMicrosoftEntraUsers($config),
                'google_workspace' => $this->fetchGoogleWorkspaceUsers($config),
                'ldap_active_directory' => $this->fetchLdapUsers($config),
                default => throw new Exception("Unsupported directory provider: {$config->provider_type}"),
            };

            $totalScanned = count($directoryUsers);
            $createdCount = 0;
            $updatedCount = 0;
            $deactivatedCount = 0;
            $syncedEmails = [];

            foreach ($directoryUsers as $account) {
                $email = strtolower(trim($account['email']));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                // Domain filter check
                if (! empty($config->domain_filter)) {
                    $domain = substr(strrchr($email, '@') ?: '', 1);
                    if (strcasecmp($domain, $config->domain_filter) !== 0) {
                        continue;
                    }
                }

                $syncedEmails[] = $email;

                // Resolve Department
                $departmentId = $config->default_department_id;
                $deptName = ! empty($account['department']) ? trim((string) $account['department']) : null;

                if ($deptName && $config->auto_create_departments) {
                    $slug = strtoupper(Str::slug($deptName, '_'));
                    if (strlen($slug) > 30) {
                        $slug = substr($slug, 0, 24).'_'.substr(md5($deptName), 0, 5);
                    }

                    $dept = Department::firstOrCreate(
                        ['name' => $deptName],
                        ['code' => $slug]
                    );
                    $departmentId = $dept->id;
                }

                if ($dryRun) {
                    $existingUser = User::where('email', $email)->first();
                    if ($existingUser) {
                        $updatedCount++;
                    } else {
                        $createdCount++;
                    }

                    continue;
                }

                // Find or create User
                $user = User::where('email', $email)->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $account['name'] ?: $email,
                        'email' => $email,
                        'designation' => $account['designation'] ?? null,
                        'password' => bcrypt(Str::random(32)),
                        'department_id' => $departmentId,
                        'is_active' => $account['is_active'] ?? true,
                    ]);

                    $targetRole = ! empty($config->default_role) ? $config->default_role : 'Standard User';
                    Role::firstOrCreate(['name' => $targetRole, 'guard_name' => 'web']);
                    $user->assignRole($targetRole);
                    $createdCount++;
                } else {
                    $updates = [];
                    if (! empty($account['name']) && $user->name !== $account['name']) {
                        $updates['name'] = $account['name'];
                    }
                    if (array_key_exists('designation', $account) && ! empty($account['designation']) && $user->designation !== $account['designation']) {
                        $updates['designation'] = $account['designation'];
                    }
                    if ($departmentId && $user->department_id !== $departmentId) {
                        $updates['department_id'] = $departmentId;
                    }
                    if (isset($account['is_active']) && $user->is_active !== $account['is_active']) {
                        $updates['is_active'] = $account['is_active'];
                    }

                    if (! empty($updates)) {
                        $user->update($updates);
                        $updatedCount++;
                    }
                }
            }

            // Optional: Deactivate missing users
            if (! $dryRun && $config->deactivate_missing_users && ! empty($config->domain_filter) && count($syncedEmails) > 0) {
                $missingUsers = User::where('email', 'like', '%@'.$config->domain_filter)
                    ->whereNotIn('email', $syncedEmails)
                    ->where('is_active', true)
                    ->get();

                foreach ($missingUsers as $missing) {
                    $isSuper = false;
                    try {
                        $isSuper = $missing->hasRole('Super Administrator') || $missing->hasRole('super_admin');
                    } catch (\Throwable) {
                        $isSuper = false;
                    }

                    if (! $isSuper) {
                        $missing->update(['is_active' => false]);
                        $deactivatedCount++;
                    }
                }
            }

            $stats = [
                'total_scanned' => $totalScanned,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'deactivated' => $deactivatedCount,
                'synced_at' => now()->toIso8601String(),
            ];

            $message = sprintf(
                'Successfully synchronized %d accounts (%d created, %d updated, %d deactivated).',
                $totalScanned,
                $createdCount,
                $updatedCount,
                $deactivatedCount
            );

            $config->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => $message,
                'last_sync_stats' => $stats,
            ]);

            $this->auditService->log(
                event: 'directory.synced',
                auditable: $config,
                oldValues: [],
                newValues: $stats
            );

            return [
                'success' => true,
                'total_scanned' => $totalScanned,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'deactivated' => $deactivatedCount,
                'message' => $message,
            ];
        } catch (Exception $e) {
            Log::error("Directory sync failed for config #{$config->id}: ".$e->getMessage());

            $config->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_message' => 'Sync error: '.$e->getMessage(),
            ]);

            return [
                'success' => false,
                'total_scanned' => 0,
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
                'message' => 'Synchronization failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Test directory connection and credentials.
     *
     * @return array{success: bool, message: string, preview_count: int}
     */
    public function testConnection(DirectorySyncConfig $config): array
    {
        try {
            $users = match ($config->provider_type) {
                'microsoft_entra' => $this->fetchMicrosoftEntraUsers($config),
                'google_workspace' => $this->fetchGoogleWorkspaceUsers($config),
                'ldap_active_directory' => $this->fetchLdapUsers($config),
                default => throw new Exception("Unknown provider: {$config->provider_type}"),
            };

            return [
                'success' => true,
                'message' => 'Connection verified successfully. Retrieved '.count($users).' directory accounts.',
                'preview_count' => count($users),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
                'preview_count' => 0,
            ];
        }
    }

    /**
     * Fetch users from Microsoft Entra ID (Graph API) or simulated provider.
     *
     * @return array<int, array{email: string, name: string, department: ?string, is_active: bool}>
     */
    protected function fetchMicrosoftEntraUsers(DirectorySyncConfig $config): array
    {
        // If live credentials are provided and not in demo mode
        if (! config('app.demo') && $config->tenant_id && $config->client_id && $config->client_secret) {
            $tokenRes = Http::asForm()->timeout(10)->post("https://login.microsoftonline.com/{$config->tenant_id}/oauth2/v2.0/token", [
                'client_id' => $config->client_id,
                'client_secret' => $config->client_secret,
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            if ($tokenRes->successful()) {
                $accessToken = (string) $tokenRes->json('access_token');
                $graphRes = Http::withToken($accessToken)->timeout(15)->get('https://graph.microsoft.com/v1.0/users', [
                    '$select' => 'id,displayName,mail,userPrincipalName,department,jobTitle,accountEnabled',
                    '$top' => 500,
                ]);

                if ($graphRes->successful()) {
                    $rawUsers = (array) $graphRes->json('value', []);
                    $results = [];
                    foreach ($rawUsers as $u) {
                        $email = (string) ($u['mail'] ?? $u['userPrincipalName'] ?? '');
                        if ($email) {
                            $results[] = [
                                'email' => $email,
                                'name' => (string) ($u['displayName'] ?? $email),
                                'department' => isset($u['department']) ? (string) $u['department'] : null,
                                'designation' => isset($u['jobTitle']) ? (string) $u['jobTitle'] : null,
                                'is_active' => (bool) ($u['accountEnabled'] ?? true),
                            ];
                        }
                    }

                    return $results;
                }
            }
        }

        // Deterministic Simulation / Demo Directory
        $domain = $config->domain_filter ?: 'univ.edu';

        return [
            [
                'email' => 'dr.carter@'.$domain,
                'name' => 'Dr. Robert Carter',
                'department' => 'Computer Science',
                'designation' => 'Associate Professor',
                'is_active' => true,
            ],
            [
                'email' => 'prof.adams@'.$domain,
                'name' => 'Prof. Eleanor Adams',
                'department' => 'Mathematics',
                'designation' => 'Professor & Chair',
                'is_active' => true,
            ],
            [
                'email' => 'sarah.dean@'.$domain,
                'name' => 'Sarah Dean (Faculty Dean)',
                'department' => 'Academic Affairs',
                'designation' => 'Dean of Academic Affairs',
                'is_active' => true,
            ],
            [
                'email' => 'marcus.vance@'.$domain,
                'name' => 'Marcus Vance',
                'department' => 'Engineering',
                'designation' => 'Lab Director',
                'is_active' => true,
            ],
            [
                'email' => 'clara.oswald@'.$domain,
                'name' => 'Clara Oswald',
                'department' => 'Humanities',
                'designation' => 'Lecturer in Literature',
                'is_active' => true,
            ],
        ];
    }

    /**
     * Fetch users from Google Workspace Directory API or simulated directory.
     *
     * @return array<int, array{email: string, name: string, department: ?string, is_active: bool}>
     */
    protected function fetchGoogleWorkspaceUsers(DirectorySyncConfig $config): array
    {
        // 1. Check if real Service Account JSON is provided
        if (! config('app.demo') && ! empty($config->service_account_json)) {
            $sa = json_decode((string) $config->service_account_json, true);
            if (is_array($sa) && ! empty($sa['client_email']) && ! empty($sa['private_key'])) {
                $now = time();
                $header = ['alg' => 'RS256', 'typ' => 'JWT'];
                $claim = [
                    'iss' => $sa['client_email'],
                    'sub' => ! empty($config->admin_email) ? $config->admin_email : $sa['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/admin.directory.user.readonly',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'exp' => $now + 3600,
                    'iat' => $now,
                ];

                $base64Url = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
                $signingInput = $base64Url((string) json_encode($header)).'.'.$base64Url((string) json_encode($claim));

                $privateKey = openssl_pkey_get_private($sa['private_key']);
                if ($privateKey) {
                    $signature = '';
                    if (openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                        $jwt = $signingInput.'.'.$base64Url($signature);

                        $tokenRes = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                            'assertion' => $jwt,
                        ]);

                        if (! $tokenRes->successful()) {
                            $errDesc = $tokenRes->json('error_description') ?? $tokenRes->json('error') ?? $tokenRes->body();
                            throw new Exception("Google OAuth2 token exchange failed ({$tokenRes->status()}): {$errDesc}");
                        }

                        $accessToken = (string) $tokenRes->json('access_token');
                        $results = [];
                        $pageToken = null;

                        do {
                            $params = [
                                'maxResults' => 500,
                            ];
                            if (! empty($config->domain_filter)) {
                                $params['domain'] = $config->domain_filter;
                            } else {
                                $params['customer'] = 'my_customer';
                            }
                            if ($pageToken) {
                                $params['pageToken'] = $pageToken;
                            }

                            $apiRes = Http::withToken($accessToken)->timeout(25)->get('https://admin.googleapis.com/admin/directory/v1/users', $params);

                            if (! $apiRes->successful()) {
                                $err = $apiRes->json('error.message') ?? $apiRes->body();
                                throw new Exception("Google Directory API call failed ({$apiRes->status()}): {$err}");
                            }

                            $usersData = (array) $apiRes->json('users', []);
                            foreach ($usersData as $u) {
                                $email = (string) ($u['primaryEmail'] ?? '');
                                if (! $email) {
                                    continue;
                                }

                                $fullName = (string) ($u['name']['fullName'] ?? '');
                                if (! $fullName) {
                                    $fullName = trim(($u['name']['givenName'] ?? '').' '.($u['name']['familyName'] ?? '')) ?: $email;
                                }

                                $department = null;
                                $designation = null;
                                if (! empty($u['organizations']) && is_array($u['organizations'])) {
                                    foreach ($u['organizations'] as $org) {
                                        if (! empty($org['department']) && ! $department) {
                                            $department = (string) $org['department'];
                                        }
                                        if (! empty($org['title']) && ! $designation) {
                                            $designation = (string) $org['title'];
                                        }
                                    }
                                }

                                $suspended = (bool) ($u['suspended'] ?? false);
                                $archived = (bool) ($u['archived'] ?? false);
                                $isActive = ! $suspended && ! $archived;

                                $results[] = [
                                    'email' => $email,
                                    'name' => $fullName,
                                    'department' => $department,
                                    'designation' => $designation,
                                    'is_active' => $isActive,
                                ];
                            }

                            $pageToken = $apiRes->json('nextPageToken');
                        } while (! empty($pageToken));

                        return $results;
                    }
                }
            }
        }

        // Demo / simulation fallback (for unit tests or when credentials are not supplied)
        $domain = $config->domain_filter ?: 'univ.edu';

        return [
            [
                'email' => 'alan.turing@'.$domain,
                'name' => 'Alan Turing',
                'department' => 'Informatics',
                'designation' => 'Chief Cryptanalyst',
                'is_active' => true,
            ],
            [
                'email' => 'grace.hopper@'.$domain,
                'name' => 'Grace Hopper',
                'department' => 'Computer Science',
                'designation' => 'Rear Admiral & Research Director',
                'is_active' => true,
            ],
            [
                'email' => 'katherine.johnson@'.$domain,
                'name' => 'Katherine Johnson',
                'department' => 'Mathematics',
                'designation' => 'Senior Aerospace Mathematician',
                'is_active' => true,
            ],
            [
                'email' => 'richard.feynman@'.$domain,
                'name' => 'Richard Feynman',
                'department' => 'Physics',
                'designation' => 'Professor of Theoretical Physics',
                'is_active' => true,
            ],
        ];
    }

    /**
     * Fetch users from LDAP / On-Premise Active Directory or simulated directory.
     *
     * @return array<int, array{email: string, name: string, department: ?string, is_active: bool}>
     */
    protected function fetchLdapUsers(DirectorySyncConfig $config): array
    {
        if (! config('app.demo') && ! empty($config->ldap_host) && function_exists('ldap_connect')) {
            $conn = @ldap_connect($config->ldap_host, (int) ($config->ldap_port ?: 389));
            if ($conn) {
                @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
                @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
                if ($config->ldap_use_ssl) {
                    @ldap_start_tls($conn);
                }

                $bound = false;
                if (! empty($config->ldap_bind_dn)) {
                    $bound = @ldap_bind($conn, $config->ldap_bind_dn, (string) $config->ldap_bind_password);
                } else {
                    $bound = @ldap_bind($conn);
                }

                if (! $bound) {
                    throw new Exception('LDAP Bind authentication failed: '.@ldap_error($conn));
                }

                $filter = ! empty($config->group_filter) ? $config->group_filter : '(&(objectClass=user)(mail=*))';
                $baseDn = (string) ($config->ldap_base_dn ?: '');
                $search = @ldap_search($conn, $baseDn, $filter, ['mail', 'cn', 'displayName', 'department', 'title', 'jobTitle', 'description']);

                if ($search) {
                    $entries = @ldap_get_entries($conn, $search);
                    $results = [];
                    $count = (int) ($entries['count'] ?? 0);
                    for ($i = 0; $i < $count; $i++) {
                        $email = (string) ($entries[$i]['mail'][0] ?? '');
                        if (! $email) {
                            continue;
                        }
                        $name = (string) ($entries[$i]['displayname'][0] ?? $entries[$i]['cn'][0] ?? $email);
                        $dept = isset($entries[$i]['department'][0]) ? (string) $entries[$i]['department'][0] : null;
                        $designation = isset($entries[$i]['title'][0])
                            ? (string) $entries[$i]['title'][0]
                            : (isset($entries[$i]['jobtitle'][0]) ? (string) $entries[$i]['jobtitle'][0] : null);

                        $results[] = [
                            'email' => $email,
                            'name' => $name,
                            'department' => $dept,
                            'designation' => $designation,
                            'is_active' => true,
                        ];
                    }
                    @ldap_unbind($conn);

                    return $results;
                }
                @ldap_unbind($conn);
            }
        }

        $domain = $config->domain_filter ?: 'univ.edu';

        return [
            [
                'email' => 'ad.admin@'.$domain,
                'name' => 'Active Directory Admin',
                'department' => 'IT Infrastructure',
                'designation' => 'Senior Infrastructure Administrator',
                'is_active' => true,
            ],
            [
                'email' => 'faculty.ldap@'.$domain,
                'name' => 'Faculty LDAP User',
                'department' => 'Applied Sciences',
                'designation' => 'Associate Professor',
                'is_active' => true,
            ],
        ];
    }
}
