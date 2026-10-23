<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalFilePortal
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('local-storage.enabled') || $request->is('files', 'files/*', 'up', 'build/*', 'favicon.ico', 'storage/*')) {
            return $next($request);
        }
        // The old public signup, storage configuration and deletion APIs must not
        // bypass the local portal's account limit or approval workflow.
        if ($request->is('api/*') || !$request->isMethod('GET')) {
            abort(404);
        }
        return redirect('/files');
    }
}
