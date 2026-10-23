<?php

use App\Http\Controllers\DirectLinkController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\ShareableLinksController;
use App\Models\FileEntry;
use Common\Core\Controllers\HomeController;
use Common\Pages\CustomPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(\App\Http\Middleware\PortalLocale::class)->group(function () {
Route::post('files/language', function (\Illuminate\Http\Request $request) {
    $data = $request->validate(['locale' => 'required|in:en,ar']);
    return redirect(auth()->check() ? '/files' : '/files/login')
        ->withCookie(cookie('portal_locale', $data['locale'], 525600, '/', null, $request->isSecure(), true, false, 'lax'));
});
Route::get('files/login', [\App\Http\Controllers\LocalStorageController::class, 'loginPage']);
Route::get('files/health', function () {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    abort_unless(is_dir(config('local-storage.root')) && is_writable(config('local-storage.root')), 503);
    return response()->json(['status' => 'ok']);
});
Route::post('files/login', [\App\Http\Controllers\LocalStorageController::class, 'login'])->middleware('throttle:5,1');
Route::group(['prefix' => 'files', 'middleware' => [\App\Http\Middleware\RequirePortalLogin::class, \App\Http\Middleware\ProtectStorageSnapshot::class]], function () {
    $controller = \App\Http\Controllers\LocalStorageController::class;
    Route::get('/', [$controller, 'index']);
    Route::post('logout', [$controller, 'logout']);
    Route::post('upload', [$controller, 'upload']);
    Route::post('upload-status', [$controller, 'uploadStatus']);
    Route::get('inventory', [$controller, 'inventory']);
    Route::get('{id}/download', [$controller, 'download'])->whereNumber('id');
    Route::get('{id}/preview', [$controller, 'preview'])->whereNumber('id');
    Route::post('{id}/delete', [$controller, 'deleteDocument'])->whereNumber('id');
    Route::post('{id}/request-deletion', [$controller, 'requestDeletion'])->whereNumber('id');
    Route::post('requests/{id}/review', [$controller, 'review'])->whereNumber('id');
    Route::post('users', [$controller, 'createUser']);
    Route::post('users/{id}/permissions', [$controller, 'updateUser'])->whereNumber('id');
    Route::post('users/{id}/delete', [$controller, 'deleteUser'])->whereNumber('id');
});

});

Route::get('test', function () {
    $entry = FileEntry::find(118);
    return $entry
        ->getDisk()
        ->temporaryUrl($entry->getStoragePath(), now()->addMinutes(30));
});

//FRONT-END ROUTES THAT NEED TO BE PRE-RENDERED
Route::get('/', LandingPageController::class);
Route::get('drive/s/{hash}', [ShareableLinksController::class, 'show']);
Route::get('d/{linkHash}/{fileHash}.{extension}', [
    DirectLinkController::class,
    'show',
]);

Route::get('contact', [HomeController::class, 'render']);
Route::get('pages/{slugOrId}', [CustomPageController::class, 'show']);
Route::get('login', [HomeController::class, 'render'])->name('login');
Route::get('register', [HomeController::class, 'render'])->name('register');
Route::get('forgot-password', [HomeController::class, 'render']);
Route::get('pricing', '\Common\Billing\PricingPageController');

//CATCH ALL ROUTES AND REDIRECT TO HOME
Route::fallback([HomeController::class, 'render']);
