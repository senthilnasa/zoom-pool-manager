<?php

namespace App\Console\Commands;

use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
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
    protected $signature = 'zpm:recordings:sync';

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

            $tokenRes = Http::timeout(10)->withHeaders(['Authorization' => 'Basic '.$basicAuth])->post($tokenUrl);
            if (! $tokenRes->successful()) {
                $this->error('Failed to authenticate with Zoom.');

                return Command::FAILURE;
            }

            $token = $tokenRes->json('access_token');
            if (! $token) {
                $this->error('No access token returned.');

                return Command::FAILURE;
            }

            // Check active managed resources
            $resources = ZoomResource::with('zoomUser')->where('managed', true)->limit(10)->get();
            $totalSynced = 0;

            foreach ($resources as $res) {
                $email = $res->zoomUser?->email;
                if (! $email) {
                    continue;
                }

                $url = "https://api.zoom.us/v2/users/{$email}/recordings?from=".now()->subDays(3)->toDateString();
                $resp = Http::timeout(10)->withHeaders(['Authorization' => 'Bearer '.$token])->get($url);

                if ($resp->successful()) {
                    $meetings = $resp->json('meetings') ?? [];
                    foreach ($meetings as $m) {
                        $recordingService->ingestCompletedRecording(['payload' => ['object' => $m]]);
                        $totalSynced++;
                    }
                }
            }

            $this->info("✓ Synced {$totalSynced} cloud recordings across host accounts.");

            return Command::SUCCESS;
        } catch (Exception $e) {
            Log::error('Recording sync error: '.$e->getMessage());
            $this->error('Recording sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
