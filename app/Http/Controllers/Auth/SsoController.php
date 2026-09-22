<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Providers\SamlIdentityProvider;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\IdentityProviderFactory;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SsoController extends Controller
{
    public function __construct(
        protected IdentityProviderFactory $factory,
        protected AuthService $authService,
        protected SamlIdentityProvider $samlProvider,
    ) {}

    /**
     * Redirect the user to the external Identity Provider.
     */
    public function redirect(string $provider): \Symfony\Component\HttpFoundation\Response
    {
        $idp = IdentityProvider::where('public_id', $provider)
            ->where('enabled', true)
            ->firstOrFail();

        $driver = $this->factory->make($idp);

        return $driver->getRedirectResponse($idp);
    }

    /**
     * Handle the OAuth / OIDC callback from Google or Microsoft.
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        $idp = IdentityProvider::where('public_id', $provider)
            ->where('enabled', true)
            ->firstOrFail();

        try {
            $driver = $this->factory->make($idp);
            $identityDto = $driver->handleCallback($idp, $request);

            $this->authService->handleSsoUser(
                provider: $idp,
                dto: $identityDto,
                ipAddress: $request->ip() ?? '127.0.0.1',
                userAgent: $request->userAgent()
            );

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Single sign-on failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Download or view SAML Service Provider metadata XML.
     */
    public function samlMetadata(string $provider): Response
    {
        $idp = IdentityProvider::where('public_id', $provider)
            ->where('enabled', true)
            ->where('driver', 'saml')
            ->firstOrFail();

        $xml = $this->samlProvider->generateSpMetadata($idp);

        return response($xml, 200, [
            'Content-Type' => 'application/samlmetadata+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="zpm-sp-metadata.xml"',
        ]);
    }

    /**
     * Handle SAML 2.0 Assertion Consumer Service (ACS) POST request.
     */
    public function samlAcs(string $provider, Request $request): RedirectResponse
    {
        $idp = IdentityProvider::where('public_id', $provider)
            ->where('enabled', true)
            ->where('driver', 'saml')
            ->firstOrFail();

        try {
            $identityDto = $this->samlProvider->handleCallback($idp, $request);

            $this->authService->handleSsoUser(
                provider: $idp,
                dto: $identityDto,
                ipAddress: $request->ip() ?? '127.0.0.1',
                userAgent: $request->userAgent()
            );

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'SAML authentication failed: '.$e->getMessage(),
            ]);
        }
    }
}
