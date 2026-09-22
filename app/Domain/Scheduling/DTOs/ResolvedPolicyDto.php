<?php

namespace App\Domain\Scheduling\DTOs;

readonly class ResolvedPolicyDto
{
    /**
     * @param  array<string, mixed>  $securitySettings
     */
    public function __construct(
        public int $bufferMinutes,
        public int $minNoticeHours,
        public int $maxAdvanceDays,
        public int $maxDurationMinutes,
        public string $aiCompanionPolicy,
        public string $recordingMode,
        public array $securitySettings = [],
    ) {}
}
