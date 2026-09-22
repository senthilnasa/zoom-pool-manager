<?php

namespace App\Domain\Auth\Providers;

use App\Domain\Auth\Contracts\IdentityProviderInterface;
use App\Domain\Auth\DTOs\UserIdentityDto;
use App\Domain\Auth\Models\IdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Azure\User;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;

class MicrosoftIdentityProvider implements IdentityProviderInterface
{
    public function getRedirectResponse(IdentityProvider $provider): Response
    {
        $this->configureSocialite($provider);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('azure');

        return $driver->scopes(['openid', 'profile', 'email', 'User.Read'])->redirect();
    }

    public function handleCallback(IdentityProvider $provider, Request $request): UserIdentityDto
    {
        $this->configureSocialite($provider);

        /** @var User $socialiteUser */
        $socialiteUser = Socialite::driver('azure')->user();

        $raw = (array) $socialiteUser->user;
        $email = (string) ($socialiteUser->getEmail() ?? $socialiteUser->user['userPrincipalName'] ?? '');
        $name = (string) ($socialiteUser->getName() ?? $email);
        $externalId = (string) $socialiteUser->getId();

        $groups = [];
        if (isset($raw['groups']) && is_array($raw['groups'])) {
            /** @var mixed $group */
            foreach ($raw['groups'] as $group) {
                if (is_string($group)) {
                    $groups[] = $group;
                }
            }
        }
        if (isset($raw['roles']) && is_array($raw['roles'])) {
            /** @var mixed $role */
            foreach ($raw['roles'] as $role) {
                if (is_string($role)) {
                    $groups[] = $role;
                }
            }
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
        Config::set('services.azure', [
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect' => route('auth.sso.callback', ['provider' => $provider->public_id]),
            'tenant' => $provider->tenant_id ?: 'common',
        ]);
    }
}
