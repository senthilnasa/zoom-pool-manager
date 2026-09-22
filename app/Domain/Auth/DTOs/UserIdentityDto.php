<?php

namespace App\Domain\Auth\DTOs;

readonly class UserIdentityDto
{
    /**
     * @param  array<string>  $groups
     * @param  array<string, mixed>  $rawAttributes
     */
    public function __construct(
        public string $externalId,
        public string $email,
        public string $name,
        public array $groups = [],
        public array $rawAttributes = [],
    ) {}
}
