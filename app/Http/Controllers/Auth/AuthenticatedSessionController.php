<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\SecurityLog;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            SecurityLog::logEvent('login_failed', [
                'severity' => 'warning',
                'ip_address' => $request->ip(),
                'action' => 'login_attempt_failed',
                'description' => 'Failed login attempt for ' . $request->email,
                'risk_level' => 'medium',
                'metadata' => [
                    'email' => $request->email,
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        // Set tenant_id in session for tenant isolation
        if (Auth::user()->tenant_id) {
            session(['tenant_id' => Auth::user()->tenant_id]);
        }

        SecurityLog::logEvent('login_success', [
            'severity' => 'info',
            'ip_address' => $request->ip(),
            'action' => 'user_logged_in',
            'description' => 'User logged in successfully',
            'user_id' => Auth::id(),
            'risk_level' => 'low',
            'metadata' => [
                'user_name' => Auth::user()->name,
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return redirect()->intended(route('acs.dashboard'));
    }

    public function destroy(Request $request)
    {
        SecurityLog::logEvent('logout', [
            'severity' => 'info',
            'ip_address' => $request->ip(),
            'action' => 'user_logged_out',
            'description' => 'User logged out',
            'user_id' => Auth::id(),
            'risk_level' => 'low',
            'metadata' => [
                'user_name' => Auth::user()->name,
            ],
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
