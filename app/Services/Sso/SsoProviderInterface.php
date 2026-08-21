<?php

namespace App\Services\Sso;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Protocol-agnostic SSO contract. The login flow only talks to this
 * interface, so swapping SAML for OIDC later = one new implementation
 * + a config flag. No controller/route/view changes needed.
 */
interface SsoProviderInterface
{
    /**
     * Kick off the SSO flow (redirect the user's browser to the IdP).
     */
    public function redirectToIdp(): RedirectResponse;

    /**
     * Handle the IdP callback (ACS). Returns the verified identity,
     * or null when the assertion is invalid / authentication failed.
     */
    public function handleCallback(): ?SsoIdentity;

    /**
     * Service Provider metadata XML (to register with the IdP admin).
     */
    public function metadata(): string;
}
