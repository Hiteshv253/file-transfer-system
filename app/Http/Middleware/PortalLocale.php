<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PortalLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->cookie('portal_locale', 'en');
        app()->setLocale(in_array($locale, ['en', 'ar'], true) ? $locale : 'en');
        return $next($request);
    }
}
