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
 * style-src still allows 'unsafe-inline' (inline styles are used app-wide);
 * moving styles to nonces is a future hardening step.
 *
 * ACCEPTED RISK (2026-09-03, decision with Jackie): Cross-Origin-Embedder-Policy
 * (COEP) is deliberately NOT set. require-corp/credentialless would force every
 * third-party subresource (AG Grid/Bootstrap from cdn.jsdelivr.net, Google Fonts)
 * to carry CORP/CORS - high breakage risk for marginal Spectre-isolation benefit
 * on an authenticated internal app. CORP (same-origin) IS set as the pragmatic
 * middle ground. Revisit only if the app stops loading third-party resources.
 *
 * UPDATE 2026-09-04 (Jackie decision): COEP require-corp + COOP same-origin now
 * SET. Google Fonts was self-hosted the same day (SRI fix) and jsdelivr sends
 * CORP: cross-origin + ACAO * on all resource types (verified), so the only
 * remaining external host fully complies. Verified via headless Chrome sweep -
 * no blocked resources, no console errors (incl. AG Grid pages + PDF viewer).
 *
 * style-src: since 2026-09-04 (round 2) 'unsafe-inline' is DROPPED from
 * style-src-elem (which governs <style> tags + <link> stylesheets) and split into
 * style-src-elem (strict, nonce) + style-src-attr 'unsafe-inline'. Round 1 failed
 * because AG Grid's normal build injects ~840KB of CSS as runtime <style> tags.
 * Fix: ag-grid-community.min.noStyle.js build (CSS stays in <link> tags) + app
 * inline style="" attrs moved to utilities.css classes + nonced <style> blocks.
 * style-src-attr keeps 'unsafe-inline' because AG Grid 31 positions rows via
 * runtime style-attribute writes (translateY) which CSP3 cannot nonce/hash -
 * style-src-attr is the spec-sanctioned allowance for style attributes only.
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
        // COEP/COOP pair (2026-09-04, Jackie decision - see docblock above).
        // require-corp: cross-origin subresources must opt in via CORP or CORS.
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net",
            // style-src-elem: governs <style> tags + <link> stylesheets - strict, nonce only.
            // style-src-attr: governs style="" attributes. AG Grid 31 writes row
            // transforms/positions via style attributes at runtime (noStyle build,
            // 2026-09-04) - style attributes CANNOT be nonced/hashed, so per CSP3
            // this is the only way to allow them while <style> injection stays blocked.
            "style-src-elem 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net",
            "style-src-attr 'unsafe-inline'",
            "font-src 'self' https://cdn.jsdelivr.net data:",
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
