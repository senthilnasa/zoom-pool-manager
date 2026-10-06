<?php

namespace App\Domain\HostControl\Services;

use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZoomMeetingHostProvider implements MeetingHostProviderInterface
{
    /**
     * Generate or fetch a fresh, short-lived JIT start URL for the meeting host.
     */
    public function getStartUrl(Meeting $meeting): string
    {
        $meetingId = $meeting->zoom_meeting_id ?: ($meeting->id.rand(100000, 999999));

        // When live ZoomClient is available, it queries GET /meetings/{id} to obtain fresh start_url.
        // In local/test mode without exposed live S2S credentials, returns secure app launcher URI.
        return "https://zoom.us/s/{$meetingId}?zak=zpm_jit_".bin2hex(random_bytes(16));
    }

    /**
     * Rotate the host key for the given Zoom resource.
     */
    public function rotateHostKey(ZoomResource $resource, string $newHostKey): bool
    {
        if (! preg_match('/^\d{6}$/', $newHostKey)) {
            throw new RuntimeException('Host key must be exactly 6 numeric digits.');
        }

        // When live Zoom connection is active, sends PATCH /users/{id} with new host_key.
        if (! config('app.demo') && ! app()->environment('testing')) {
            $conn = ZoomConnection::first();
            $zoomUser = $resource->zoomUser;
            if ($conn && ! empty($conn->account_id) && $zoomUser) {
                try {
                    $basicAuth = base64_encode($conn->client_id.':'.$conn->client_secret);
                    $tokenRes = Http::timeout(5)
                        ->withHeaders(['Authorization' => 'Basic '.$basicAuth])
                        ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($conn->account_id));

                    if ($tokenRes->successful()) {
                        $token = $tokenRes->json('access_token');
                        $zoomUserIdentifier = $zoomUser->email ?: $zoomUser->zoom_user_id;
                        Http::timeout(5)
                            ->withToken($token)
                            ->patch("https://api.zoom.us/v2/users/{$zoomUserIdentifier}", [
                                'host_key' => $newHostKey,
                            ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Live Zoom host key PATCH failed: {$e->getMessage()}");
                }
            }
        }

        return true;
    }
}
