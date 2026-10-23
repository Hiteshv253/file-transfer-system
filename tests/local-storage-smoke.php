<?php

// Run: php tests/local-storage-smoke.php. Test records are rolled back.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());

use App\Http\Controllers\LocalStorageController;
use App\Models\StoredDocument;
use App\Models\User;
use Common\Auth\Permissions\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function asUser($user, $data = [], $file = null) {
    Auth::setUser($user);
    $request = Request::create('/files', 'POST', $data, [], $file ? ['file' => $file] : []);
    $request->setUserResolver(fn() => $user);
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
    return $request;
}
function denied($callback, $status, $message) {
    try { $callback(); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        check($e->getStatusCode() === $status, $message); return;
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        check($status === 404, $message); return;
    }
    throw new RuntimeException($message.' (not denied)');
}

$root = storage_path('app/local-storage-smoke-'.Str::uuid());
config(['local-storage.root' => $root, 'local-storage.sync_writes' => true, 'local-storage.lock_path' => $root.'/snapshot.lock']);
$disk = Storage::build(['driver' => 'local', 'root' => $root, 'throw' => true]);
$controller = new LocalStorageController();
DB::beginTransaction();
try {
    $suffix = Str::random(10);
    $admin = User::create(['name' => 'Test admin', 'email' => "$suffix-admin@example.test", 'password' => Str::random(24)]);
    $permission = Permission::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $admin->permissions()->attach($permission->id);
    $owner = User::create(['name' => 'Test owner', 'email' => "$suffix-owner@example.test", 'password' => Str::random(24)]);
    $shared = User::create(['name' => 'Test shared', 'email' => "$suffix-shared@example.test", 'password' => Str::random(24)]);
    $other = User::create(['name' => 'Test other', 'email' => "$suffix-other@example.test", 'password' => Str::random(24)]);
    $pdf = UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    $controller->upload(asUser($owner, ['share_with' => [$shared->id]], $pdf));
    $doc = StoredDocument::where('owner_id', $owner->id)->firstOrFail();
    check(str_starts_with($doc->path, now('Asia/Kolkata')->format('Y/m/Y-m-d').'/') && $disk->exists($doc->path), 'Upload is stored privately in a date folder');
    check(StoredDocument::accessibleTo($shared)->whereKey($doc->id)->exists(), 'Shared user can access the document');
    check(!StoredDocument::accessibleTo($other)->whereKey($doc->id)->exists(), 'Unrelated user cannot list the document');
    denied(fn() => $controller->download(asUser($other), $doc->id), 404, 'Unrelated user cannot download by ID');
    check($controller->download(asUser($shared, ['preview' => 1]), $doc->id)->headers->get('Content-Type') === 'application/pdf', 'Shared user can preview PDF');
    denied(fn() => $controller->requestDeletion(asUser($shared, ['reason' => 'test']), $doc->id), 403, 'Recipient cannot request deletion of another user’s file');
    $controller->requestDeletion(asUser($owner, ['reason' => 'Old copy']), $doc->id);
    $id = DB::table('document_deletion_requests')->where('document_id', $doc->id)->value('id');
    check($disk->exists($doc->path) && StoredDocument::find($doc->id), 'Pending deletion keeps file accessible');
    denied(fn() => $controller->requestDeletion(asUser($owner, ['reason' => 'duplicate']), $doc->id), 409, 'Duplicate pending request is blocked');
    denied(fn() => $controller->review(asUser($owner, ['decision' => 'approved']), $id), 403, 'Regular user cannot approve deletion');
    $controller->review(asUser($admin, ['decision' => 'rejected', 'review_note' => 'Retain']), $id);
    check($disk->exists($doc->path) && DB::table('document_deletion_requests')->find($id)->status === 'rejected', 'Rejected deletion retains file and audit history');
    $controller->requestDeletion(asUser($owner, ['reason' => 'Confirmed removal']), $doc->id);
    $id = DB::table('document_deletion_requests')->where('document_id', $doc->id)->where('status', 'pending')->value('id');
    $controller->review(asUser($admin, ['decision' => 'approved']), $id);
    check(!$disk->exists($doc->path) && !StoredDocument::find($doc->id) && StoredDocument::withTrashed()->find($doc->id), 'Approval removes physical file and retains metadata');
    check(DB::table('document_deletion_requests')->find($id)->reviewed_by === $admin->id, 'Audit records reviewer identity');
    denied(fn() => $controller->review(asUser($admin, ['decision' => 'rejected']), $id), 409, 'Reviewed request cannot be changed');
    denied(fn() => $controller->createUser(asUser($owner)), 403, 'Regular user cannot create accounts');
    config(['local-storage.user_limit' => User::all()->filter(fn($u) => !$u->hasPermission('admin'))->count()]);
    denied(fn() => $controller->createUser(asUser($admin, ['name' => 'Over quota', 'email' => "$suffix-quota@example.test", 'password' => 'LongPassword123', 'password_confirmation' => 'LongPassword123'])), 422, 'Regular user quota is enforced');
    config(['local-storage.user_limit' => 100]);
    $controller->createUser(asUser($admin, ['name' => 'New user', 'email' => "$suffix-new@example.test", 'password' => 'LongPassword123', 'password_confirmation' => 'LongPassword123']));
    $newUser = User::where('email', "$suffix-new@example.test")->firstOrFail();
    check(\Illuminate\Support\Facades\Hash::check('LongPassword123', $newUser->password) && !$newUser->hasPermission('admin'), 'Created account has hashed password and no admin privileges');
    $page = $controller->index(asUser($admin))->render();
    check(str_contains($page, 'Deletion approvals') && str_contains($page, 'Create a user'), 'Admin dashboard renders approvals and user management');
    $page = $controller->index(asUser($owner))->render();
    check(!str_contains($page, 'Create a user account') && !str_contains($page, 'Approve deletion'), 'Regular dashboard hides admin controls');
    foreach (['photo.png' => "\x89PNG\r\n\x1a\n", 'sound.mp3' => 'ID3 audio test', 'video.mp4' => "\x00\x00\x00\x18ftypmp42", 'notes.txt' => 'Example text'] as $name => $contents) {
        $request = asUser($owner, ['share_with' => [$shared->id]], UploadedFile::fake()->createWithContent($name, $contents));
        $request->headers->set('Accept', 'application/json');
        $response = $controller->upload($request);
        $result = $response->getData(true);
        $document = StoredDocument::findOrFail($result['document']['id']);
        check($response->getStatusCode() === 201 && $document->name === $name && $disk->get($document->path) === $contents
            && StoredDocument::accessibleTo($shared)->whereKey($document->id)->exists(), 'Async upload preserves bytes and sharing for '.$name);
    }
    config(['local-storage.lock_writes' => true]);
    $lock = fopen(config('local-storage.lock_path'), 'c+');
    flock($lock, LOCK_EX);
    $uploadRequest = Request::create('/files/upload', 'POST');
    $gate = new \App\Http\Middleware\ProtectStorageSnapshot();
    $response = $gate->handle($uploadRequest, fn() => throw new RuntimeException('Write bypassed exclusive backup lock'));
    check($response->getStatusCode() === 503, 'Consistent backup blocks concurrent uploads without writing or deleting files');
    $reviewRequest = Request::create('/files/requests/1/review', 'POST');
    check($gate->handle($reviewRequest, fn() => throw new RuntimeException('Deletion bypassed backup lock'))->getStatusCode() === 503, 'Deletion reviews cannot alter files during a backup');
    flock($lock, LOCK_UN); fclose($lock);
    check($gate->handle($uploadRequest, fn() => response('ok'))->getStatusCode() === 200, 'Uploads resume when the backup releases its lock');
    echo "All local storage smoke checks passed.\n";
} finally {
    DB::rollBack();
    if (str_starts_with($root, storage_path('app/local-storage-smoke-'))) {
        \Illuminate\Support\Facades\File::deleteDirectory($root);
    }
}
