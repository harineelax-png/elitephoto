<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        if (session('is_admin') || Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            session(['is_admin' => true]);
            return redirect()->intended(route('admin.dashboard'))->with('success', 'Welcome back, Administrator!');
        }

        // Demo fallback check
        if ($request->email === 'admin@luminaframes.com' && $request->password === 'admin123') {
            session(['is_admin' => true]);
            return redirect()->route('admin.dashboard')->with('success', 'Logged in as Administrator.');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function quickLogin()
    {
        session(['is_admin' => true]);
        return redirect()->route('admin.dashboard')->with('success', 'Quick Admin access granted for development.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        session()->forget('is_admin');

        return redirect()->route('admin.login')->with('success', 'You have been logged out.');
    }
}
