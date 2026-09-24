<?php

namespace App\Console\Commands;

use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCloudRecordingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:recordings:sync
                            {--days=30 : Number of days to look back for recordings (up to 90)}
                            {--user= : Optional specific Zoom user email or ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize newly completed cloud recordings from Zoom pooled host accounts';

    /**
     * Execute the console command.
     */
    public function handle(CloudRecordingService $recordingService): int
    {
        $this->info('Checking for completed cloud recordings on Zoom...');

        /** @var ZoomConnection|null $conn */
        $conn = ZoomConnection::first();
        if (! $conn || empty($conn->account_id) || empty($conn->client_id) || empty($conn->client_secret)) {
            $this->warn('Zoom credentials unconfigured, skipping recordings sync.');

            return Command::SUCCESS;
        }

        if (config('app.demo')) {
            $this->info('Demo mode active: recordings sync simulated.');

            return Command::SUCCESS;
        }

        try {
            $basicAuth = base64_encode($conn->client_id.':'.$conn->client_secret);
            $tokenUrl = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($conn->account_id);

            $tokenRes = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Basic '.$basicAuth,
                    'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                ])
                ->post($tokenUrl);

            if (! $tokenRes->successful()) {
                $errMsg = "Failed to authenticate with Zoom: HTTP {$tokenRes->status()} - {$tokenRes->body()}";
                Log::error($errMsg);
                $this->error($errMsg);

                return Command::FAILURE;
            }

            $token = $tokenRes->json('access_token');
            if (! $token) {
                $this->error('No access token returned from Zoom.');

                return Command::FAILURE;
            }

            // Determine resources/hosts to sync
            $resourcesQuery = ZoomResource::with('zoomUser')->where('managed', true);
            $specificUser = $this->option('user');
            if ($specificUser) {
                $resourcesQuery->whereHas('zoomUser', function ($q) use ($specificUser) {
                    $q->where('email', $specificUser)->orWhere('zoom_user_id', $specificUser);
                });
            }
            $resources = $resourcesQuery->get();

            // Also check ZoomUsers directly in case some are not mapped to ZoomResource
            if ($resources->isEmpty() && $specificUser) {
                $zoomUser = ZoomUser::where('email', $specificUser)->orWhere('zoom_user_id', $specificUser)->first();
                if ($zoomUser) {
                    $res = ZoomResource::firstOrCreate(['zoom_user_id' => $zoomUser->id], [
                        'managed' => true,
                        'status' => 'active',
                        'participant_capacity' => 100,
                    ]);
                    $res->setRelation('zoomUser', $zoomUser);
                    $resources = collect([$res]);
                }
            }

            $lookbackDays = min(90, max(1, (int) ($this->option('days') ?: 30)));
            $totalSynced = 0;

            // Zoom API allows at most 30 days per query range. Slice lookback into 30-day windows.
            $windows = [];
            for ($offset = 0; $offset < $lookbackDays; $offset += 30) {
                $windowEnd = now()->subDays($offset)->toDateString();
                $windowStart = now()->subDays(min($lookbackDays, $offset + 29))->toDateString();
                $windows[] = ['from' => $windowStart, 'to' => $windowEnd];
            }

            $this->info("Scanning {$resources->count()} host accounts over past {$lookbackDays} days in ".count($windows).' date window(s)...');

            foreach ($resources as $res) {
                $zoomUser = $res->zoomUser;
                $email = $zoomUser?->email;
                $zoomUserId = $zoomUser?->zoom_user_id;
                $userIdentifier = $zoomUserId ?: $email;

                if (! $userIdentifier) {
                    continue;
                }

                foreach ($windows as $win) {
                    $nextPageToken = '';
                    do {
                        $queryParams = [
                            'from' => $win['from'],
                            'to' => $win['to'],
                            'page_size' => 300,
                        ];
                        if (! empty($nextPageToken)) {
                            $queryParams['next_page_token'] = $nextPageToken;
                        }

                        $url = 'https://api.zoom.us/v2/users/'.urlencode($userIdentifier).'/recordings';
                        $resp = Http::timeout(20)
                            ->withHeaders([
                                'Authorization' => 'Bearer '.$token,
                                'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                            ])
                            ->get($url, $queryParams);

                        if ($resp->failed()) {
                            $status = $resp->status();
                            if ($status === 403) {
                                $this->warn("Host {$email} (403): Zoom app may be missing 'recording:read:admin' scope.");
                            } elseif ($status !== 404) {
                                Log::warning("Zoom recording sync error for host {$email}: HTTP {$status} - {$resp->body()}");
                            }
                            break;
                        }

                        $data = $resp->json();
                        $meetings = $data['meetings'] ?? [];
                        foreach ($meetings as $m) {
                            $recordingService->ingestCompletedRecording([
                                'payload' => ['object' => $m],
                                'zoom_resource_id' => $res->id,
                                'host_email' => $email,
                            ]);
                            $totalSynced++;
                        }

                        $nextPageToken = (string) ($data['next_page_token'] ?? '');
                    } while (! empty($nextPageToken));
                }
            }

            $conn->update([
                'last_sync_at' => now(),
                'last_success_at' => now(),
                'last_error' => null,
            ]);

            $this->info("✓ Synced {$totalSynced} cloud recordings across {$resources->count()} host accounts.");

            return Command::SUCCESS;
        } catch (Exception $e) {
            Log::error('Recording sync exception: '.$e->getMessage());
            $this->error('Recording sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
