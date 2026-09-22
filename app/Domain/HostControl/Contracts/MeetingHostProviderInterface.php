<?php

namespace App\Domain\HostControl\Contracts;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ZoomResource;

interface MeetingHostProviderInterface
{
    /**
     * Generate or fetch a fresh, short-lived JIT start URL for the meeting host.
     * Note: This URL must never be persisted, emailed, or exposed in plain text in logs/API.
     */
    public function getStartUrl(Meeting $meeting): string;

    /**
     * Rotate the host key for the given Zoom resource.
     * Generates a new 6-digit numeric key and applies it to Zoom.
     */
    public function rotateHostKey(ZoomResource $resource, string $newHostKey): bool;
}
