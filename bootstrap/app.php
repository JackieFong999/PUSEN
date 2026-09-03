<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs OUTSIDE the CSRF middleware: re-issues the XSRF-TOKEN cookie with
        // HttpOnly=true (2026-09-03, ZAP "Cookie No HttpOnly Flag"). The app's JS
        // never reads that cookie - tokens go via {{ csrf_token() }} headers/inputs.
        $middleware->web(prepend: [
            \App\Http\Middleware\XsrfCookieHttpOnly::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'role.access' => \App\Http\Middleware\CheckRoleAccess::class,
        ]);
        // The SAML ACS endpoint receives POSTs from the IdP (third party) —
        // it can't carry a CSRF token, so exclude it (standard for SSO).
        $middleware->validateCsrfTokens(except: [
            'login/sso/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON errors for API paths AND any fetch()/AJAX request that asks for JSON
        // (Accept: application/json). Without expectsJson(), validation failures on
        // /admin/* returned a 302/HTML redirect, so the frontend showed a broken
        // "Unexpected token '<'" toast instead of the real message (UAT CC-05, 2026-09-01).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
