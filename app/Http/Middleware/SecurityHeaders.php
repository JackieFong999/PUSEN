<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds standard security headers to every response.
 *
 * CSP: inline <script> blocks carry a per-request nonce (View::share 'cspNonce',
 * injected into every inline <script> tag in the Blade views), so script-src can
 * drop 'unsafe-inline' - injected scripts without the nonce are blocked by the
 * browser. External CDN scripts (jsdelivr) stay host-allowlisted.
 * style-src: since 2026-09-04 all inline style="" attributes were moved to local
 * utility classes (public/css/utilities.css, loaded from 'self') and every
 * <style> block carries the same per-request nonce, so style-src can also drop
 * 'unsafe-inline'. CSSOM style writes from JS (el.style.x = ...) are not governed
 * by CSP and keep working (display toggles etc.).
 *
 * ACCEPTED RISK (2026-09-03, decision with Jackie): Cross-Origin-Embedder-Policy
 * (COEP) is deliberately NOT set. require-corp/credentialless would force every
 * third-party subresource (AG Grid/Bootstrap from cdn.jsdelivr.net, Google Fonts)
 * to carry CORP/CORS - high breakage risk for marginal Spectre-isolation benefit
 * on an authenticated internal app. CORP (same-origin) IS set as the pragmatic
 * middle ground. Revisit only if the app stops loading third-party resources.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Per-request CSP nonce shared with every Blade view (2026-09-03, ZAP
        // "CSP: script-src unsafe-inline" finding). 18 random bytes -> 24-char base64.
        // MUST be shared BEFORE $next() renders the view.
        $cspNonce = base64_encode(random_bytes(18));
        View::share('cspNonce', $cspNonce);

        $response = $next($request);
        // HSTS: only enforced by browsers when the site is served over HTTPS.
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // CORP: only same-origin pages may load our resources (2026-09-03, ZAP
        // "Cross-Origin-Resource-Policy Header Missing"). Pairs with XFO/CSP
        // frame-ancestors - blocks cross-origin embedding + Spectre-class reads.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net",
            "style-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Symfony auto-fills 3xx redirects with a "Redirecting to…" HTML body
        // (meta-refresh + link). It duplicates the target URL - for /login/sso that
        // includes the full SAML request + RelayState - and triggers ZAP "Big Redirect"
        // info-leak warnings. Modern clients only need the Location header, so strip
        // the body (2026-09-03, ZAP 10044 x3).
        if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) {
            $response->setContent(null);
        }

        return $response;
    }
}
