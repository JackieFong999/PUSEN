<?php

namespace App\Http\Controllers\Auth;

use App\Services\Sso\SsoProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SSO login flow (SAML today, protocol-agnostic by design).
 *
 * Both SSO and local password logins land in the same web guard / Staff
 * model, so the rest of the app (role middleware, sidebar, audit) is
 * unaffected. Reuses LoginController's audit + homePath().
 */
class SsoLoginController extends LoginController
{
    public function __construct(private readonly SsoProviderInterface $sso) {}

    /** GET /login/sso — bounce the browser to the IdP. */
    public function redirectToIdp()
    {
        if (Auth::check()) {
            return redirect()->intended($this->homePath());
        }

        return $this->sso->redirectToIdp();
    }

    /** POST /login/sso/callback (ACS) — consume the assertion and log in. */
    public function callback(Request $request)
    {
        $identity = $this->sso->handleCallback();

        if (! $identity) {
            $this->logLogin('unknown', 'N', $request, 'Invalid SAML assertion', 'SSO');
            return $this->fail('SSO login failed. Please try again or sign in with your Staff ID and password.');
        }

        $staff = DB::connection('pusen')->table('tblStaff')
            ->where('SSO_Email', $identity->email)
            ->first();

        if (! $staff) {
            $this->logLogin($identity->email, 'N', $request, 'No staff mapped to SSO email', 'SSO');
            return $this->fail('Your SSO account is not linked to a staff account. Please contact the administrator.');
        }

        if ((int) $staff->status === 1) {
            $this->logLogin($identity->email, 'N', $request, 'Account disabled', 'SSO');
            return $this->fail('Your account is disabled. Please contact the administrator.');
        }

        Auth::loginUsingId($staff->Staff_Id);
        $request->session()->regenerate();

        $this->logLogin($staff->Staff_Id, 'Y', $request, '', 'SSO');

        return redirect()->intended($this->homePath());
    }

    /** GET /login/sso/metadata — SP metadata to hand to the IdP admin. */
    public function metadata()
    {
        return response($this->sso->metadata(), 200, ['Content-Type' => 'text/xml']);
    }

    private function fail(string $message)
    {
        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
