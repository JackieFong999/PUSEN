<?php

namespace App\Providers;

use App\Auth\PusenStaffUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
