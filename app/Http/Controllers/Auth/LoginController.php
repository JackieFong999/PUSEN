<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    /**
     * Home path after login.
     *
     * 2026-08-19 (demo): Dashboard is temporarily hidden from the menu, so
     * every role lands on SEN Search. Restore the role split ('/dashboard'
     * for SA/AU) when the Dashboards item is unhidden.
     */
    protected function homePath(): string
    {
        return '/admin/sen-search';
    }

    /**
     * Show the login form (redirects away when already logged in).
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->intended($this->homePath());
        }

        return response(view('auth.login'))
            ->header('Cache-Control', 'no-store, private, max-age=0');
    }

    /**
     * Attempt login against tblStaff (Staff_Id + Password).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'staff_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $staffId = trim($credentials['staff_id']);

        // 'Staff_Id' matches the real column name (retrieved by the provider);
        // 'password' is skipped by retrieveByCredentials and compared in
        // PusenStaffUserProvider::validateCredentials().
        $attempt = Auth::attempt([
            'Staff_Id' => $staffId,
            'password' => $credentials['password'],
        ]);

        if (! $attempt) {
            $this->logLogin($staffId, 'N', $request, $this->failureReason($staffId, $credentials['password']));
            return back()
                ->withErrors(['login' => 'Invalid Staff ID or password.'])
                ->withInput($request->only('staff_id'));
        }

        $this->logLogin($staffId, 'Y', $request);

        // protect against session fixation
        $request->session()->regenerate();

        return redirect()->intended($this->homePath());
    }

    /**
     * Write a login attempt to tblLogin_Log (audit trail).
     * Status: 'Y' = success, 'N' = failed attempt. Remarks holds the
     * failure reason for failed attempts ('' on success).
     * Method: LOCAL (Staff ID + password) or SSO.
     */
    protected function logLogin(string $staffId, string $status, Request $request, string $remarks = '', string $method = 'LOCAL'): void
    {
        try {
            DB::connection('pusen')->table('tblLogin_Log')->insert([
                'Staff_Id'   => $staffId,
                'Login_Time' => now(),
                'Status'     => $status,
                'IP'         => $request->ip(),
                'Browser'    => $this->browserName($request->userAgent()),
                'Method'     => $method,
                'Remarks'    => $remarks,
            ]);
        } catch (\Throwable $e) {
            // logging must never break the login flow
            report($e);
        }
    }

    /** Underlying reason for a failed login (written to tblLogin_Log.Remarks).
     *  The screen always shows the generic message - specifics are audit-only. */
    private function failureReason(string $staffId, string $password): string
    {
        $staff = DB::connection('pusen')->table('tblStaff')->where('Staff_Id', $staffId)->first();
        if (! $staff) {
            return 'Staff ID not found';
        }
        if ((int) $staff->status === 1) {
            return 'Account disabled';
        }
        return 'Invalid password';
    }

    /** Short browser label (fits the varchar(10) Browser column).
     *  Order matters: Edge/Opera UAs also contain "Chrome" (Chromium-based),
     *  so they must be matched BEFORE the generic Chrome check. */
    private function browserName(?string $ua): string
    {
        $ua = strtolower((string) $ua);
        if (str_contains($ua, 'edg')) {
            return 'Edge';
        }
        if (str_contains($ua, 'opr') || str_contains($ua, 'opera')) {
            return 'Opera';
        }
        if (str_contains($ua, 'chrome')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'firefox')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'safari')) {
            return 'Safari';
        }
        if (str_contains($ua, 'msie') || str_contains($ua, 'trident')) {
            return 'IE';
        }
        return 'Other';
    }

    /**
     * Log the staff member out.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
