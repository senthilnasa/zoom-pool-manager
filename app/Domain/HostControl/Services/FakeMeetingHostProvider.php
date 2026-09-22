<?php

namespace App\Domain\HostControl\Services;

use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ZoomResource;

class FakeMeetingHostProvider implements MeetingHostProviderInterface
{
    /**
     * Generate simulated JIT start URL in demo mode.
     */
    public function getStartUrl(Meeting $meeting): string
    {
        $token = bin2hex(random_bytes(16));

        return "https://zoom.us/s/demo_{$meeting->public_id}?zak=demo_zak_{$token}";
    }

    /**
     * Simulate host key rotation in demo mode.
     */
    public function rotateHostKey(ZoomResource $resource, string $newHostKey): bool
    {
        if ($resource->zoomUser) {
            $resource->zoomUser->update([
                'host_key' => $newHostKey,
                'synced_at' => now(),
            ]);
        }

        return true;
    }
}
