<?php

namespace App\Domain\Scheduling\DTOs;

readonly class ConflictCheckResult
{
    /**
     * @param  array<int, array{type: string, message: string, details?: array<string, mixed>}>  $conflicts
     */
    public function __construct(
        public bool $hasConflict,
        public array $conflicts = [],
        public int $availableResourceCount = 0,
    ) {}
}
