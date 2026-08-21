<?php

namespace App\Providers;

use App\Auth\PusenStaffUserProvider;
use App\Services\Sso\SamlSsoProvider;
use App\Services\Sso\SsoProviderInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SSO provider binding: swap the implementation here (or via the
        // SSO_PROVIDER env) when the school's protocol is confirmed.
        $this->app->bind(SsoProviderInterface::class, function ($app) {
            $provider = config('sso.provider', 'saml');

            return match ($provider) {
                'saml' => new SamlSsoProvider(new \OneLogin\Saml2\Auth(config('sso.saml', []))),
                default => throw new \RuntimeException("Unsupported SSO provider: {$provider}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom user provider for login against the legacy tblStaff table
        // (plain-text password comparison, see PusenStaffUserProvider).
        Auth::provider('pusen-staff', function ($app, array $config) {
            return new PusenStaffUserProvider($app['hash'], $config['model']);
        });
    }
}
