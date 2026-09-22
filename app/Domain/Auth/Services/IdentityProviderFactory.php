<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\IdentityProviderInterface;
use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Providers\GoogleIdentityProvider;
use App\Domain\Auth\Providers\MicrosoftIdentityProvider;
use App\Domain\Auth\Providers\SamlIdentityProvider;
use InvalidArgumentException;

class IdentityProviderFactory
{
    public function make(IdentityProvider $provider): IdentityProviderInterface
    {
        return match (strtolower($provider->driver)) {
            'google' => app(GoogleIdentityProvider::class),
            'microsoft', 'azure' => app(MicrosoftIdentityProvider::class),
            'saml' => app(SamlIdentityProvider::class),
            default => throw new InvalidArgumentException("Unsupported identity provider driver: [{$provider->driver}]"),
        };
    }
}
