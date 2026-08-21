<?php

namespace App\Services\Sso;

use OneLogin\Saml2\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * SAML 2.0 implementation of SsoProviderInterface, wrapping onelogin/php-saml.
 *
 * Configuration lives in config/sso.php (SAML settings block) and is driven
 * by .env values, so pointing at the school's real IdP later is config-only.
 */
class SamlSsoProvider implements SsoProviderInterface
{
    public function __construct(private readonly Auth $saml) {}

    public function redirectToIdp(): RedirectResponse
    {
        // $stay = true -> login() returns the IdP redirect URL instead of
        // sending headers + exit() itself, so Laravel keeps control.
        $url = $this->saml->login(null, [], false, false, true);

        return redirect()->away($url);
    }

    public function handleCallback(): ?SsoIdentity
    {
        $this->saml->processResponse();

        if (! $this->saml->isAuthenticated()) {
            return null;
        }

        $attrs = $this->saml->getAttributes();
        $email = $attrs['email'][0]
            ?? $attrs['mail'][0]
            ?? $attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0]
            ?? null;
        $email = $email ?: $this->saml->getNameId();

        if (! $email) {
            return null;
        }

        return new SsoIdentity(strtolower(trim($email)));
    }

    public function metadata(): string
    {
        return $this->saml->getSettings()->getSPMetadata();
    }
}
