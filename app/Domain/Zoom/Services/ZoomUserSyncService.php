<?php

namespace App\Domain\Zoom\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomUserSyncService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Synchronize host user accounts from Zoom into Zoom Pool Manager.
     *
     * @return array{success: bool, count: int, message: string, synced_at: string}
     */
    public function syncUsers(): array
    {
        /** @var ZoomConnection|null $connection */
        $connection = ZoomConnection::first();

        if (! $connection || empty($connection->account_id) || empty($connection->client_id) || empty($connection->client_secret)) {
            return [
                'success' => false,
                'count' => 0,
                'message' => 'Zoom credentials are not configured. Please complete OAuth configuration in Settings > Zoom API first.',
                'synced_at' => now()->toIso8601String(),
            ];
        }

        // Demo Mode Simulation
        if (config('app.demo')) {
            return $this->simulateDemoSync($connection);
        }

        try {
            // 1. Obtain Server-to-Server OAuth access token
            $basicAuth = base64_encode($connection->client_id.':'.$connection->client_secret);
            $tokenUrl = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($connection->account_id);

            $tokenResponse = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Basic '.$basicAuth,
                    'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                ])
                ->post($tokenUrl);

            if (! $tokenResponse->successful()) {
                return [
                    'success' => false,
                    'count' => 0,
                    'message' => 'Failed to authenticate with Zoom OAuth: HTTP '.$tokenResponse->status().' '.$tokenResponse->body(),
                    'synced_at' => now()->toIso8601String(),
                ];
            }

            /** @var array{access_token?: string} $tokenJson */
            $tokenJson = $tokenResponse->json();
            $accessToken = $tokenJson['access_token'] ?? null;

            if (! $accessToken) {
                return [
                    'success' => false,
                    'count' => 0,
                    'message' => 'Zoom OAuth token response did not contain an access_token.',
                    'synced_at' => now()->toIso8601String(),
                ];
            }

            // 2. Query Zoom Users API
            $usersResponse = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                ])
                ->get('https://api.zoom.us/v2/users', [
                    'page_size' => 300,
                    'status' => 'active',
                ]);

            if (! $usersResponse->successful()) {
                return [
                    'success' => false,
                    'count' => 0,
                    'message' => 'Failed to fetch users from Zoom API: HTTP '.$usersResponse->status().' '.$usersResponse->body(),
                    'synced_at' => now()->toIso8601String(),
                ];
            }

            /** @var array{users?: array<int, array<string, mixed>>} $usersData */
            $usersData = $usersResponse->json();
            $zoomUsers = $usersData['users'] ?? [];
            $syncedCount = 0;

            foreach ($zoomUsers as $u) {
                $zoomUserId = (string) ($u['id'] ?? '');
                $email = (string) ($u['email'] ?? '');

                if (empty($zoomUserId) || empty($email)) {
                    continue;
                }

                $userType = (int) ($u['type'] ?? 2); // 1 = Basic, 2 = Licensed

                // Upsert ZoomUser
                /** @var ZoomUser $dbUser */
                $dbUser = ZoomUser::updateOrCreate(
                    [
                        'connection_id' => $connection->id,
                        'zoom_user_id' => $zoomUserId,
                    ],
                    [
                        'email' => $email,
                        'first_name' => $u['first_name'] ?? null,
                        'last_name' => $u['last_name'] ?? null,
                        'user_type' => $userType,
                        'status' => 'active',
                        'timezone' => $u['timezone'] ?? null,
                        'synced_at' => now(),
                        'raw_metadata' => $u,
                    ]
                );

                // Upsert ZoomResource
                ZoomResource::firstOrCreate(
                    ['zoom_user_id' => $dbUser->id],
                    [
                        'managed' => true,
                        'status' => 'active',
                        'priority' => 10,
                        'participant_capacity' => $userType === 2 ? 300 : 100,
                        'cloud_recording' => true,
                        'transcript' => true,
                        'ai_companion' => false,
                        'max_concurrent' => 1,
                        'capabilities_checked_at' => now(),
                    ]
                );

                $syncedCount++;
            }

            $connection->update([
                'last_sync_at' => now(),
                'last_success_at' => now(),
                'last_error' => null,
            ]);

            $this->auditService->log(
                event: 'zoom.users.synced',
                auditable: $connection,
                newValues: ['synced_users_count' => $syncedCount]
            );

            return [
                'success' => true,
                'count' => $syncedCount,
                'message' => "Successfully synchronized {$syncedCount} Zoom host accounts from your organization.",
                'synced_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Zoom user sync error: '.$e->getMessage());

            return [
                'success' => false,
                'count' => 0,
                'message' => 'Sync failed: '.$e->getMessage(),
                'synced_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Seed realistic institutional Zoom host accounts in Demo Mode.
     *
     * @return array{success: bool, count: int, message: string, synced_at: string}
     */
    protected function simulateDemoSync(ZoomConnection $connection): array
    {
        $demoHosts = [
            [
                'id' => 'zm_demo_host_01',
                'email' => 'zoom-host-1@univ.edu',
                'first_name' => 'General Classroom',
                'last_name' => 'Host 01',
                'type' => 2,
                'capacity' => 300,
                'cloud_recording' => true,
                'ai_companion' => true,
            ],
            [
                'id' => 'zm_demo_host_02',
                'email' => 'zoom-host-2@univ.edu',
                'first_name' => 'General Classroom',
                'last_name' => 'Host 02',
                'type' => 2,
                'capacity' => 300,
                'cloud_recording' => true,
                'ai_companion' => false,
            ],
            [
                'id' => 'zm_demo_host_03',
                'email' => 'zoom-host-3@univ.edu',
                'first_name' => 'Science Lecture',
                'last_name' => 'Host 03',
                'type' => 2,
                'capacity' => 300,
                'cloud_recording' => true,
                'ai_companion' => true,
            ],
            [
                'id' => 'zm_demo_auditorium',
                'email' => 'zoom-auditorium@univ.edu',
                'first_name' => 'Main Auditorium',
                'last_name' => 'Hall Host',
                'type' => 2,
                'capacity' => 500,
                'cloud_recording' => true,
                'ai_companion' => true,
            ],
            [
                'id' => 'zm_demo_webinar',
                'email' => 'zoom-webinar@univ.edu',
                'first_name' => 'Institutional',
                'last_name' => 'Webinar Host',
                'type' => 2,
                'capacity' => 1000,
                'cloud_recording' => true,
                'ai_companion' => true,
            ],
        ];

        $count = 0;
        foreach ($demoHosts as $h) {
            /** @var ZoomUser $dbUser */
            $dbUser = ZoomUser::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'zoom_user_id' => $h['id'],
                ],
                [
                    'email' => $h['email'],
                    'first_name' => $h['first_name'],
                    'last_name' => $h['last_name'],
                    'user_type' => $h['type'],
                    'status' => 'active',
                    'timezone' => 'America/New_York',
                    'synced_at' => now(),
                ]
            );

            ZoomResource::updateOrCreate(
                ['zoom_user_id' => $dbUser->id],
                [
                    'managed' => true,
                    'status' => 'active',
                    'priority' => 10,
                    'participant_capacity' => $h['capacity'],
                    'cloud_recording' => $h['cloud_recording'],
                    'transcript' => true,
                    'ai_companion' => $h['ai_companion'],
                    'max_concurrent' => 1,
                    'capabilities_checked_at' => now(),
                ]
            );

            $count++;
        }

        $connection->update([
            'last_sync_at' => now(),
            'last_success_at' => now(),
        ]);

        return [
            'success' => true,
            'count' => $count,
            'message' => "Successfully synchronized {$count} Zoom host accounts (Demo Simulation).",
            'synced_at' => now()->toIso8601String(),
        ];
    }
}
