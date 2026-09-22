<?php

namespace App\Domain\Meetings\Services;

class AgendaMarkerService
{
    /**
     * Marker format for Zoom agenda idempotency deduplication.
     */
    public const MARKER_PREFIX = '[ZPM:';

    public const MARKER_SUFFIX = ']';

    /**
     * Append the idempotency marker to the meeting agenda/description.
     */
    public function appendMarker(?string $agenda, string $meetingPublicId): string
    {
        $marker = self::MARKER_PREFIX.$meetingPublicId.self::MARKER_SUFFIX;

        $trimmed = trim($agenda ?? '');

        if (empty($trimmed)) {
            return $marker;
        }

        // Avoid appending duplicate marker
        if (str_contains($trimmed, $marker)) {
            return $trimmed;
        }

        return $trimmed."\n\n".$marker;
    }

    /**
     * Extract the ZPM meeting public identifier from a Zoom agenda/description string.
     */
    public function extractMarker(?string $agenda): ?string
    {
        if (empty($agenda)) {
            return null;
        }

        if (preg_match('/\[ZPM:([A-Za-z0-9_-]{20,32})\]/', $agenda, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if a Zoom agenda matches the expected meeting identifier.
     */
    public function matchesMeeting(?string $agenda, string $meetingPublicId): bool
    {
        if (empty($agenda)) {
            return false;
        }

        $marker = self::MARKER_PREFIX.$meetingPublicId.self::MARKER_SUFFIX;

        return str_contains($agenda, $marker);
    }

    /**
     * Strip the ZPM marker from an agenda/description string.
     */
    public function stripMarker(?string $agenda): string
    {
        if (empty($agenda)) {
            return '';
        }

        $cleaned = preg_replace('/\[ZPM:[A-Za-z0-9_-]{20,32}\]/', '', $agenda);

        return trim((string) $cleaned);
    }
}
