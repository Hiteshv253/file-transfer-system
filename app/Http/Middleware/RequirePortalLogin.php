<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePortalLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return redirect('/files/login');
        }
        abort_if($request->user()->banned_at || $request->user()->portal_deleted_at, 403, __('This account is disabled.'));
        if ((int) $request->session()->get('portal_session_version', 0) !== (int) $request->user()->portal_session_version) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->expectsJson() ? response()->json(['message' => __('Please sign in again.')], 401) : redirect('/files/login');
        }
        return $next($request);
    }
}
