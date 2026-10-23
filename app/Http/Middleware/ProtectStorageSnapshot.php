<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProtectStorageSnapshot
{
    public function handle(Request $request, Closure $next)
    {
        $writesFiles = $request->isMethod('POST') && ($request->is('files/upload') || $request->is('files/requests/*/review') || $request->is('files/*/delete') || $request->is('files/bulk-delete') || $request->is('files/requests/bulk-review') || $request->is('files/folders') || $request->is('files/folders/tree') || $request->is('files/folders/*/delete'));
        if (!config('local-storage.lock_writes') || !$writesFiles) return $next($request);
        $handle = fopen(config('local-storage.lock_path'), 'c+');
        if (!$handle) abort(503, 'Storage lock is unavailable. Contact the super admin.');
        if (!flock($handle, LOCK_SH | LOCK_NB)) {
            fclose($handle);
            return response()->json(['message' => 'A storage backup is in progress. Files are safe; retry this upload or review shortly.'], 503, ['Retry-After' => '30']);
        }
        try { return $next($request); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
