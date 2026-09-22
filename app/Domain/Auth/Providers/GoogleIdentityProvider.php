<?php

namespace App\Domain\Auth\Providers;

use App\Domain\Auth\Contracts\IdentityProviderInterface;
use App\Domain\Auth\DTOs\UserIdentityDto;
use App\Domain\Auth\Models\IdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\Response;

class GoogleIdentityProvider implements IdentityProviderInterface
{
    public function getRedirectResponse(IdentityProvider $provider): Response
    {
        $this->configureSocialite($provider);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function handleCallback(IdentityProvider $provider, Request $request): UserIdentityDto
    {
        $this->configureSocialite($provider);

        /** @var User $socialiteUser */
        $socialiteUser = Socialite::driver('google')->user();

        $raw = (array) $socialiteUser->user;
        $email = (string) ($socialiteUser->getEmail() ?? '');
        $name = (string) ($socialiteUser->getName() ?? $email);
        $externalId = (string) $socialiteUser->getId();

        // Google Workspace Hosted Domain claim 'hd' if present
        $groups = [];
        if (! empty($raw['hd'])) {
            $groups[] = (string) $raw['hd'];
        }

        return new UserIdentityDto(
            externalId: $externalId,
            email: $email,
            name: $name,
            groups: $groups,
            rawAttributes: $raw,
        );
    }

    protected function configureSocialite(IdentityProvider $provider): void
    {
        Config::set('services.google', [
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect' => route('auth.sso.callback', ['provider' => $provider->public_id]),
        ]);
    }
}
