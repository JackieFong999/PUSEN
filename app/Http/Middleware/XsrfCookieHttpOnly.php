<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-issues Laravel's XSRF-TOKEN cookie with the HttpOnly flag set.
 *
 * Why: Laravel ships XSRF-TOKEN WITHOUT HttpOnly by design, so that JavaScript
 * libraries (e.g. axios) can read it and echo it back as X-XSRF-TOKEN. This app
 * never reads that cookie - every AJAX call sends the token server-side via
 * Blade ({{ csrf_token() }} in the X-CSRF-TOKEN header) and forms use @csrf
 * hidden inputs. The JS-readable cookie is therefore pure attack surface:
 * HttpOnly stops any future XSS from exfiltrating it via document.cookie
 * (defense in depth). Server-side CSRF validation is unaffected - the cookie
 * was never a validation source, only a JS convenience carrier.
 *
 * Must be prepended to the 'web' group (runs OUTSIDE the CSRF middleware) so it
 * executes AFTER the CSRF middleware has added the cookie on the response path.
 * Added 2026-09-03 - ZAP baseline finding "Cookie No HttpOnly Flag" (x4).
 */
class XsrfCookieHttpOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== 'XSRF-TOKEN') {
                continue;
            }

            // Rebuild the identical cookie with httpOnly=true. Symfony's
            // setCookie() replaces by (domain, path, name), so no duplicate.
            $response->headers->setCookie(new Cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                true, // httpOnly - the whole point of this middleware
                false, // raw
                $cookie->getSameSite(),
                $cookie->isPartitioned(),
            ));

            break;
        }

        return $response;
    }
}
