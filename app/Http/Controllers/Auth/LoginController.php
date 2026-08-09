<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form (redirects away when already logged in).
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->intended('/dashboard');
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

        // 'Staff_Id' matches the real column name (retrieved by the provider);
        // 'password' is skipped by retrieveByCredentials and compared in
        // PusenStaffUserProvider::validateCredentials().
        $attempt = Auth::attempt([
            'Staff_Id' => trim($credentials['staff_id']),
            'password' => $credentials['password'],
        ]);

        if (! $attempt) {
            return back()
                ->withErrors(['login' => 'Invalid Staff ID or password.'])
                ->withInput($request->only('staff_id'));
        }

        // protect against session fixation
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
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
