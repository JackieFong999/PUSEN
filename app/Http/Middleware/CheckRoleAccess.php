<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict application access by tblStaff.Role_Id.
 *
 * - SA (Super Administrator): full access.
 * - AU (Admin User): Dashboards, SEN Search, Create SEN only.
 * - KS (Key Staff) and any other role: SEN Search only.
 *
 * Visiting any other module renders the "Access Deny" dialog and bounces
 * back to the SEN Search screen.
 */
class CheckRoleAccess
{
    /**
     * Allowed paths per role. '*' = full access.
     * A request path matches when it equals an entry or starts with "entry/".
     */
    public const ROLE_ACCESS = [
        'SA' => ['*'],
        'AU' => ['/', 'dashboard', 'admin/sen-search', 'admin/create-sen', 'admin/sen-doc'],
        'KS' => ['admin/sen-search'],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->Role_Id;

        // Unknown roles fall back to the most restricted set (KS).
        $allowed = self::ROLE_ACCESS[$role] ?? self::ROLE_ACCESS['KS'];

        if (in_array('*', $allowed, true)) {
            return $next($request);
        }

        $path = $request->path();
        foreach ($allowed as $entry) {
            if ($path === $entry || str_starts_with($path, $entry.'/')) {
                return $next($request);
            }
        }

        return response()->view('errors.access-denied', [], 403);
    }
}
