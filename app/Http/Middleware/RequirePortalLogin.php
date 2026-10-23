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
        return $next($request);
    }
}
