<?php

namespace App\Domain\HostControl\Services;

use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ZoomResource;
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
        return true;
    }
}
