<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session('is_admin', false)) {
            return $next($request);
        }

        if (Auth::check()) {
            return $next($request);
        }

        return redirect()->route('admin.login')->with('error', 'Please log in as an administrator to access the admin panel.');
    }
}
