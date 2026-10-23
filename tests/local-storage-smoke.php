<?php

// Run: php tests/local-storage-smoke.php. Test records are rolled back.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());

use App\Http\Controllers\LocalStorageController;
use App\Models\StoredDocument;
use App\Models\StoredFolder;
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
    $localeGate = new \App\Http\Middleware\PortalLocale();
    $arabicRequest = Request::create('/files', 'GET', [], ['portal_locale' => 'ar']);
    $localeGate->handle($arabicRequest, fn() => response('ok'));
    $page = $controller->index(asUser($admin))->render();
    check(str_contains($page, 'lang="ar" dir="rtl"') && str_contains($page, 'مكتبة ملفات الفريق')
        && str_contains($page, 'إنشاء حساب مستخدم') && str_contains($page, 'data-portal-panel="files"'), 'Arabic admin dashboard renders RTL without translating machine identifiers');
    $page = $controller->index(asUser($owner))->render();
    check(str_contains($page, 'مكتبة ملفاتك') && !str_contains($page, 'إنشاء حساب مستخدم'), 'Arabic regular dashboard preserves account permissions');
    Auth::logout();
    $page = $controller->loginPage()->render();
    check(str_contains($page, 'مرحبًا بك في خزنة الملفات') && str_contains($page, '/files/language'), 'Arabic login offers both languages before authentication');
    $localeGate->handle(Request::create('/files', 'GET', [], ['portal_locale' => 'invalid']), fn() => response('ok'));
    check(app()->getLocale() === 'en', 'Unsupported language falls back to English');
    foreach (['photo.png' => "\x89PNG\r\n\x1a\n", 'sound.mp3' => 'ID3 audio test', 'video.mp4' => "\x00\x00\x00\x18ftypmp42", 'notes.txt' => 'Example text'] as $name => $contents) {
        $request = asUser($owner, ['share_with' => [$shared->id]], UploadedFile::fake()->createWithContent($name, $contents));
        $request->headers->set('Accept', 'application/json');
        $response = $controller->upload($request);
        $result = $response->getData(true);
        $document = StoredDocument::findOrFail($result['document']['id']);
        check($response->getStatusCode() === 201 && $document->name === $name && $disk->get($document->path) === $contents
            && StoredDocument::accessibleTo($shared)->whereKey($document->id)->exists(), 'Async upload preserves bytes and sharing for '.$name);
    }
    $controller->createFolder(asUser($owner, ['name'=>'مشاريع']));
    $folderRoot = StoredFolder::where('owner_id',$owner->id)->where('name','مشاريع')->firstOrFail();
    $controller->createFolder(asUser($owner, ['name'=>'مشاريع']));
    check(StoredFolder::where('owner_id',$owner->id)->where('name','مشاريع')->count() === 1, 'Repeated folder creation does not create duplicate root folders');
    $controller->createFolderTree(asUser($owner, ['parent_id'=>$folderRoot->id,'paths'=>['Empty/Child']]));
    check(StoredFolder::where('owner_id',$owner->id)->where('name','Child')->exists(), 'Empty subfolder trees are preserved');
    $folderKey = (string) Str::uuid();
    $controller->upload(asUser($owner, ['parent_folder_id'=>$folderRoot->id,'relative_path'=>'Client A/reports/report.txt','upload_key'=>$folderKey,'share_with'=>[$shared->id]], UploadedFile::fake()->createWithContent('report.txt','Report A')));
    $folderDocA = StoredDocument::where('upload_key',$folderKey)->firstOrFail();
    $controller->upload(asUser($owner, ['parent_folder_id'=>$folderRoot->id,'relative_path'=>'Client A/archive/report.txt'], UploadedFile::fake()->createWithContent('report.txt','Report B')));
    $folderDocB = StoredDocument::where('owner_id',$owner->id)->latest('id')->firstOrFail();
    check($folderDocA->folder_id !== $folderDocB->folder_id && $disk->get($folderDocA->path)==='Report A' && $disk->get($folderDocB->path)==='Report B', 'Nested folders preserve names and never overwrite identical file names');
    $controller->upload(asUser($owner, ['parent_folder_id'=>$folderRoot->id,'relative_path'=>'Client A/reports/report.txt','upload_key'=>$folderKey], UploadedFile::fake()->createWithContent('report.txt','Report A')));
    check(StoredDocument::where('upload_key',$folderKey)->count() === 1, 'Nested upload retries retain a single receipt');
    denied(fn()=>$controller->upload(asUser($owner,['parent_folder_id'=>$folderRoot->id,'relative_path'=>'Other/report.txt','upload_key'=>$folderKey],UploadedFile::fake()->createWithContent('report.txt','Report A'))),409,'A receipt cannot be reused for another folder');
    $nested = $controller->index(asUser($owner,['folder'=>$folderDocA->folder_id]))->getData();
    check(collect($nested['files']->items())->pluck('id')->contains($folderDocA->id) && !collect($nested['files']->items())->pluck('id')->contains($folderDocB->id), 'Folder browsing shows only files in the selected folder');
    check($nested['breadcrumbs']->pluck('name')->all() === ['مشاريع','Client A','reports'], 'Folder breadcrumb preserves original names and hierarchy');
    check(StoredFolder::accessibleTo($shared)->whereKey($folderRoot->id)->exists() && StoredFolder::accessibleTo($shared)->whereKey($folderDocA->folder_id)->exists(), 'Shared file recipients can navigate its ancestor folders');
    check(!StoredFolder::accessibleTo($other)->whereKey($folderRoot->id)->exists(), 'Unrelated users cannot browse private folders');
    denied(fn()=>$controller->createFolder(asUser($shared,['parent_id'=>$folderRoot->id,'name'=>'Forbidden'])),403,'Shared folder visibility does not grant write access');
    denied(fn()=>$controller->upload(asUser($owner,['relative_path'=>'Project/../../outside.txt'],UploadedFile::fake()->createWithContent('outside.txt','Unsafe'))),422,'Traversal in folder uploads is rejected before writing bytes');
    denied(fn()=>$controller->createFolder(asUser($owner,['name'=>'../outside'])),422,'Folder creation rejects traversal names');
    denied(fn()=>$controller->deleteFolder(asUser($admin),$folderRoot->id),422,'Nonempty folders cannot be deleted accidentally');
    $emptyChild = StoredFolder::where('owner_id',$owner->id)->where('name','Child')->firstOrFail();
    $controller->deleteFolder(asUser($admin),$emptyChild->id);
    check(!StoredFolder::find($emptyChild->id) && StoredFolder::withTrashed()->find($emptyChild->id), 'Deleting an empty folder retains its audit metadata');
    if ($qaDir = getenv('FILEVAULT_QA_DIR')) {
        if (!is_dir($qaDir)) mkdir($qaDir, 0700, true);
        foreach (['en-files', 'en-users', 'en-upload', 'ar-files'] as $variant) {
            [$locale, $tab] = explode('-', $variant);
            app()->setLocale($locale);
            $html = $controller->index(asUser($admin, ['tab' => $tab]))->render();
            $html = preg_replace_callback('~<link rel="stylesheet" href="/css/([^"?]+)[^"]*">~', fn($match) => '<style>'.file_get_contents(public_path('css/'.$match[1])).'</style>', $html);
            $html = preg_replace_callback('~<script src="/js/([^"?]+)[^"]*" defer></script>~', fn($match) => '<script>'.file_get_contents(public_path('js/'.$match[1])).'</script>', $html);
            file_put_contents($qaDir.'/'.$variant.'.html', $html);
        }
        app()->setLocale('en');
    }
    denied(fn() => $controller->resetUserPassword(asUser($owner), $newUser->id), 403, 'Regular user cannot reset another password');
    denied(fn() => $controller->resetUserPassword(asUser($admin, ['password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123']), $admin->id), 403, 'Password editor cannot reset a super admin');
    $controller->resetUserPassword(asUser($admin, ['password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123']), $newUser->id);
    $newUser->refresh();
    check(\Illuminate\Support\Facades\Hash::check('ChangedPassword123', $newUser->password) && !\Illuminate\Support\Facades\Hash::check('LongPassword123', $newUser->password), 'Admin resets user password using a stored hash');
    $oldSession = new \Illuminate\Session\Store('portal-test', new \Illuminate\Session\ArraySessionHandler(120));
    $oldSession->start();$oldSession->put('portal_session_version', 0);
    $oldRequest = asUser($newUser);$oldRequest->setLaravelSession($oldSession);
    check((new \App\Http\Middleware\RequirePortalLogin())->handle($oldRequest, fn() => response('bad'))->getStatusCode() === 302, 'Password reset invalidates previous user sessions');
    $bulkDocuments = [];
    for ($n = 0; $n < 6; $n++) {
        $controller->upload(asUser($owner, [], UploadedFile::fake()->createWithContent('bulk-'.$n.'.txt', 'Bulk operation fixture '.$n)));
        $bulkDocuments[] = StoredDocument::where('owner_id', $owner->id)->latest('id')->firstOrFail();
    }
    denied(fn() => $controller->bulkDelete(asUser($owner, ['ids' => [$bulkDocuments[0]->id]])), 403, 'Bulk delete is restricted to super admin');
    denied(fn() => $controller->bulkDelete(asUser($admin, ['ids' => [$bulkDocuments[0]->id, 999999999]])), 409, 'Stale bulk selection fails before deleting any file');
    check($disk->exists($bulkDocuments[0]->path), 'Invalid bulk selection retains valid file bytes');
    $controller->bulkDelete(asUser($admin, ['ids' => [$bulkDocuments[0]->id, $bulkDocuments[0]->id, $bulkDocuments[1]->id]]));
    check(!$disk->exists($bulkDocuments[0]->path) && !$disk->exists($bulkDocuments[1]->path), 'Bulk delete deduplicates grid/list selection and deletes selected files');
    $reviewIds = [];
    foreach ([$bulkDocuments[2], $bulkDocuments[3]] as $bulkDocument) {
        $controller->requestDeletion(asUser($owner, ['reason' => 'Bulk test']), $bulkDocument->id);
        $reviewIds[] = DB::table('document_deletion_requests')->where('document_id', $bulkDocument->id)->where('status', 'pending')->value('id');
    }
    denied(fn() => $controller->bulkReview(asUser($owner, ['ids' => $reviewIds, 'decision' => 'approved'])), 403, 'Regular user cannot bulk approve requests');
    $controller->bulkReview(asUser($admin, ['ids' => $reviewIds, 'decision' => 'rejected', 'review_note' => 'Keep files']));
    check(DB::table('document_deletion_requests')->whereIn('id', $reviewIds)->where('status', 'rejected')->count() === 2 && $disk->exists($bulkDocuments[2]->path), 'Bulk rejection retains file bytes and records decisions');
    denied(fn() => $controller->bulkReview(asUser($admin, ['ids' => $reviewIds, 'decision' => 'approved'])), 409, 'Reviewed requests cannot be bulk processed again');
    $reviewIds = [];
    foreach ([$bulkDocuments[2], $bulkDocuments[3]] as $bulkDocument) {
        $controller->requestDeletion(asUser($owner, ['reason' => 'Approved bulk test']), $bulkDocument->id);
        $reviewIds[] = DB::table('document_deletion_requests')->where('document_id', $bulkDocument->id)->where('status', 'pending')->value('id');
    }
    $controller->bulkReview(asUser($admin, ['ids' => $reviewIds, 'decision' => 'approved']));
    check(DB::table('document_deletion_requests')->whereIn('id', $reviewIds)->where('status', 'approved')->where('reviewed_by', $admin->id)->count() === 2 && !$disk->exists($bulkDocuments[2]->path) && !$disk->exists($bulkDocuments[3]->path), 'Bulk approval deletes selected files and records the admin');
    // File visibility and actions must be enforced by the server, including direct URLs.
    $active = StoredDocument::where('owner_id', $owner->id)->firstOrFail();
    $controller->updateUser(asUser($admin, ['permissions' => ['view_own']]), $owner->id);
    $owner->refresh();
    check(StoredDocument::accessibleTo($owner)->whereKey($active->id)->exists(), 'View-own permission retains owned documents');
    denied(fn() => $controller->download(asUser($owner), $active->id), 403, 'Download permission cannot be bypassed by a direct URL');
    denied(fn() => $controller->preview(asUser($owner), $active->id), 403, 'Preview requires its own permission');
    denied(fn() => $controller->upload(asUser($owner), ), 403, 'Upload permission is enforced before accepting bytes');
    denied(fn() => $controller->deleteDocument(asUser($owner), $active->id), 403, 'Direct deletion requires explicit permission');
    $page = $controller->index(asUser($owner))->render();
    check(!str_contains($page, 'id="upload-form"') && !str_contains($page, 'href="/files/'.$active->id.'/download"'), 'Revoked controls disappear from the dashboard');
    $controller->updateUser(asUser($admin, ['permissions' => []]), $shared->id);
    $shared->refresh();
    check(StoredDocument::accessibleTo($shared)->count() === 0, 'Empty permissions hide shared files too');
    $controller->updateUser(asUser($admin, ['permissions' => ['view_all', 'preview']]), $shared->id);
    $shared->refresh();
    check(StoredDocument::accessibleTo($shared)->whereKey($active->id)->exists(), 'View-all permission deliberately expands visibility');
    check(str_contains($controller->preview(asUser($shared), $active->id)->render(), 'File preview'), 'Authorized preview page renders');
    denied(fn() => $controller->download(asUser($other, ['preview' => 1]), $active->id), 404, 'Preview stream cannot expose an inaccessible document');
    $controller->updateUser(asUser($admin, ['permissions' => ['view_own', 'delete']]), $owner->id);
    $owner->refresh();
    $controller->deleteDocument(asUser($owner), $active->id);
    check(!StoredDocument::find($active->id) && !$disk->exists($active->path), 'Granted direct deletion removes accessible file without an approval step');
    check(DB::table('document_deletion_requests')->where('document_id', $active->id)->where('reviewed_by', $owner->id)->where('status', 'approved')->exists(), 'Direct deletion records the actor in audit history');
    $another = StoredDocument::where('owner_id', $owner->id)->firstOrFail();
    $controller->deleteDocument(asUser($admin), $another->id);
    check(!$disk->exists($another->path), 'Super admin can directly delete team files');
    denied(fn() => $controller->deleteUser(asUser($owner), $other->id), 403, 'Regular user cannot remove accounts');
    denied(fn() => $controller->deleteUser(asUser($admin), $admin->id), 403, 'Super admin account cannot be removed through user management');
    denied(fn() => $controller->updateUser(asUser($admin, ['permissions' => []]), $admin->id), 403, 'User permissions editor cannot change a super admin');
    $controller->deleteUser(asUser($admin), $owner->id);
    $owner->refresh();
    check($owner->portal_deleted_at && StoredDocument::where('owner_id', $owner->id)->exists(), 'Removing user retains owned files for super admin');
    denied(fn() => (new \App\Http\Middleware\RequirePortalLogin())->handle(asUser($owner), fn() => response('bad')), 403, 'Removing user revokes an existing session immediately');
    denied(fn() => $controller->download(asUser($owner), $another->id), 403, 'Removed user cannot access files');
    config(['local-storage.lock_writes' => true]);
    $lock = fopen(config('local-storage.lock_path'), 'c+');
    flock($lock, LOCK_EX);
    $uploadRequest = Request::create('/files/upload', 'POST');
    $gate = new \App\Http\Middleware\ProtectStorageSnapshot();
    $response = $gate->handle($uploadRequest, fn() => throw new RuntimeException('Write bypassed exclusive backup lock'));
    check($response->getStatusCode() === 503, 'Consistent backup blocks concurrent uploads without writing or deleting files');
    $reviewRequest = Request::create('/files/requests/1/review', 'POST');
    check($gate->handle($reviewRequest, fn() => throw new RuntimeException('Deletion bypassed backup lock'))->getStatusCode() === 503, 'Deletion reviews cannot alter files during a backup');
    check($gate->handle(Request::create('/files/1/delete', 'POST'), fn() => throw new RuntimeException('Direct deletion bypassed backup lock'))->getStatusCode() === 503, 'Direct deletion respects the consistent backup lock');
    check($gate->handle(Request::create('/files/bulk-delete', 'POST'), fn() => throw new RuntimeException('Bulk deletion bypassed backup lock'))->getStatusCode() === 503, 'Bulk deletion respects the backup lock');
    check($gate->handle(Request::create('/files/requests/bulk-review', 'POST'), fn() => throw new RuntimeException('Bulk review bypassed backup lock'))->getStatusCode() === 503, 'Bulk review respects the backup lock');
    flock($lock, LOCK_UN); fclose($lock);
    check($gate->handle($uploadRequest, fn() => response('ok'))->getStatusCode() === 200, 'Uploads resume when the backup releases its lock');
    echo "All local storage smoke checks passed.\n";
} finally {
    DB::rollBack();
    if (str_starts_with($root, storage_path('app/local-storage-smoke-'))) {
        \Illuminate\Support\Facades\File::deleteDirectory($root);
    }
}
