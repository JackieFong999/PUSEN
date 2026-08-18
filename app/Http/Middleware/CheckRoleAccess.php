<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict application access by tblStaff.Role_Id.
 *
 * - SA (Super Administrator) and AU (Admin User): full access.
 * - KS (Key Staff) and any other role: SEN Search only. Visiting any other
 *   module renders the "Access Deny" dialog and bounces back to SEN Search.
 */
class CheckRoleAccess
{
    /** Roles with full access to every module. */
    public const FULL_ACCESS_ROLES = ['SA', 'AU'];

    /** Route prefixes allowed for restricted roles (KS etc.). */
    public const RESTRICTED_ALLOWED_PREFIXES = [
        'admin/sen-search',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->Role_Id, self::FULL_ACCESS_ROLES, true)) {
            return $next($request);
        }

        // Restricted role (KS or unknown): SEN Search only.
        $path = $request->path();
        foreach (self::RESTRICTED_ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        return response()->view('errors.access-denied', [], 403);
    }
}
