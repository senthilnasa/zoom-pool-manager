<?php

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\DTOs\UserIdentityDto;
use App\Domain\Auth\Models\IdentityProvider;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface IdentityProviderInterface
{
    /**
     * Get the redirect response to the identity provider.
     */
    public function getRedirectResponse(IdentityProvider $provider): Response;

    /**
     * Process the incoming callback request and return normalized user identity.
     */
    public function handleCallback(IdentityProvider $provider, Request $request): UserIdentityDto;
}
