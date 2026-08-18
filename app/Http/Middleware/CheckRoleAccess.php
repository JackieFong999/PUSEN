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
 * - KS (Key Staff) and any other role: SEN Search + read-only SEN case view.
 *   KS may open /admin/create-sen ONLY via ?mode=view (the SEN Search View
 *   button) to look at a case and its documents; create/edit/save/upload are
 *   denied, as is direct URL access to the Create SEN screen.
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

    /** Roles that may use the full Create SEN screen (create/edit/save). */
    private const FULL_CREATE_SEN_ROLES = ['SA', 'AU'];

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

        // Restricted roles (KS etc.): Create SEN is read-only and only reachable
        // from the SEN Search View button (?mode=view). The student-info lookup
        // and document preview are read-only helpers the view screen needs.
        if (! in_array($role, self::FULL_CREATE_SEN_ROLES, true)) {
            if ($path === 'admin/create-sen' || str_starts_with($path, 'admin/create-sen/')) {
                $isReadOnlyHelper = $path === 'admin/create-sen/student-info';
                $isViewMode = $path === 'admin/create-sen' && $request->input('mode') === 'view';
                if ($isReadOnlyHelper || $isViewMode) {
                    return $next($request);
                }
                return $this->deny();
            }

            if ($path === 'admin/sen-doc' || str_starts_with($path, 'admin/sen-doc/')) {
                return $next($request);
            }
        }

        foreach ($allowed as $entry) {
            if ($path === $entry || str_starts_with($path, $entry.'/')) {
                return $next($request);
            }
        }

        return $this->deny();
    }

    private function deny(): Response
    {
        return response()->view('errors.access-denied', [], 403);
    }
}
