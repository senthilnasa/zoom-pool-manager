<?php

namespace App\Domain\Zoom\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Zoom\Models\ZoomConnection;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomConfigurationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get or create the primary Zoom connection.
     */
    public function getPrimaryConnection(): ZoomConnection
    {
        /** @var ZoomConnection|null $connection */
        $connection = ZoomConnection::first();

        if (! $connection) {
            $connection = ZoomConnection::create([
                'name' => 'Primary Zoom Account',
                'account_id' => '',
                'client_id' => '',
                'client_secret' => '',
                'enabled' => true,
                'status' => 'unconfigured',
            ]);
        }

        return $connection;
    }

    /**
     * Get safe configuration details with masked secrets.
     *
     * @return array<string, mixed>
     */
    public function getSafeConfiguration(): array
    {
        $conn = $this->getPrimaryConnection();

        return [
            'public_id' => $conn->public_id,
            'name' => $conn->name,
            'account_id' => $conn->account_id,
            'client_id' => $conn->client_id,
            'has_client_secret' => ! empty($conn->client_secret),
            'has_webhook_secret' => ! empty($conn->webhook_secret_token) || ! empty(config('zoom.webhook_secret_token')),
            'enabled' => (bool) $conn->enabled,
            'status' => $conn->status ?? 'unconfigured',
            'granted_scopes' => $conn->granted_scopes ?? [],
            'last_sync_at' => $conn->last_sync_at?->toIso8601String(),
            'last_success_at' => $conn->last_success_at?->toIso8601String(),
            'last_error' => $conn->last_error,
            'last_error_at' => $conn->last_error_at?->toIso8601String(),
            'is_demo' => (bool) config('app.demo'),
            'marketplace_url' => (string) config('zoom.marketplace_url', 'https://marketplace.zoom.us/develop/'),
            'scopes_docs_url' => (string) config('zoom.scopes_docs_url', 'https://developers.zoom.us/docs/integrations/oauth-scopes-overview/'),
            'webhook_url' => url('/webhooks/zoom/'.$conn->public_id),
            'canonical_webhook_url' => url('/webhooks/zoom'),
            'required_scopes' => config('zoom.required_scopes', []),
            'recommended_scopes' => config('zoom.recommended_scopes', []),
            'webhook_events' => config('zoom.webhook_events', []),
        ];
    }

    /**
     * Update Zoom connection credentials and settings.
     *
     * @param  array{name?: string, account_id?: string, client_id?: string, client_secret?: ?string, webhook_secret_token?: ?string, enabled?: bool}  $data
     */
    public function updateConfiguration(array $data): ZoomConnection
    {
        $conn = $this->getPrimaryConnection();

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['account_id'])) {
            $updateData['account_id'] = trim($data['account_id']);
        }

        if (isset($data['client_id'])) {
            $updateData['client_id'] = trim($data['client_id']);
        }

        if (! empty($data['client_secret'])) {
            $updateData['client_secret'] = trim($data['client_secret']);
        }

        if (isset($data['webhook_secret_token'])) {
            $updateData['webhook_secret_token'] = trim($data['webhook_secret_token']);
        }

        if (isset($data['enabled'])) {
            $updateData['enabled'] = (bool) $data['enabled'];
        }

        $conn->update($updateData);

        $this->auditService->log(
            event: 'zoom.connection.updated',
            auditable: $conn,
            newValues: [
                'name' => $conn->name,
                'account_id' => $conn->account_id,
                'client_id' => $conn->client_id,
                'enabled' => $conn->enabled,
            ]
        );

        return $conn;
    }

    /**
     * Test the Zoom connection via OAuth credentials exchange.
     *
     * @return array{success: bool, status: string, message: string, scopes: array<int, string>, checked_at: string}
     */
    public function testConnection(?ZoomConnection $connection = null): array
    {
        $conn = $connection ?? $this->getPrimaryConnection();

        if (empty($conn->account_id) || empty($conn->client_id) || empty($conn->client_secret)) {
            $conn->update([
                'status' => 'configuration_required',
                'last_error' => 'Account ID, Client ID, and Client Secret must be configured before testing.',
                'last_error_at' => now(),
            ]);

            return [
                'success' => false,
                'status' => 'configuration_required',
                'message' => 'Configuration incomplete. Please provide Account ID, Client ID, and Client Secret.',
                'scopes' => [],
                'checked_at' => now()->toIso8601String(),
            ];
        }

        // Demo Mode Simulation
        if (config('app.demo')) {
            $demoScopes = [
                'meeting:read:admin',
                'meeting:write:admin',
                'user:read:admin',
                'user:write:admin',
                'recording:read:admin',
                'report:read:admin',
            ];
            $conn->update([
                'status' => 'connected',
                'granted_scopes' => $demoScopes,
                'last_success_at' => now(),
                'last_error' => null,
            ]);

            return [
                'success' => true,
                'status' => 'connected',
                'message' => 'Demo Connection verified successfully (Simulated Zoom Provider).',
                'scopes' => $demoScopes,
                'checked_at' => now()->toIso8601String(),
            ];
        }

        try {
            $basicAuth = base64_encode($conn->client_id.':'.$conn->client_secret);
            $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($conn->account_id);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Basic '.$basicAuth,
                    'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                ])
                ->post($url);

            if ($response->successful()) {
                /** @var array{scope?: string} $json */
                $json = $response->json();
                $scopeStr = $json['scope'] ?? '';
                $scopes = array_values(array_filter(explode(' ', $scopeStr)));

                $conn->update([
                    'status' => 'connected',
                    'granted_scopes' => $scopes,
                    'last_success_at' => now(),
                    'last_error' => null,
                ]);

                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Zoom Server-to-Server OAuth connection established successfully.',
                    'scopes' => $scopes,
                    'checked_at' => now()->toIso8601String(),
                ];
            }

            $errorMsg = 'Zoom OAuth returned HTTP '.$response->status().': '.$response->body();
            $conn->update([
                'status' => 'error',
                'last_error' => $errorMsg,
                'last_error_at' => now(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => $errorMsg,
                'scopes' => [],
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Zoom connection test error: '.$e->getMessage());

            $conn->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
                'last_error_at' => now(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Connection test failed: '.$e->getMessage(),
                'scopes' => [],
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }
}
