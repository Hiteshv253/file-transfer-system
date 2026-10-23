<?php

// Run: php tests/local-storage-volume.php. Uses isolated files and rolls back test rows.
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

function ensure($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function requestAs($user, $data = [], $file = null) {
    Auth::setUser($user);
    $request = Request::create('/files', 'POST', $data, [], $file ? ['file' => $file] : []);
    $request->setUserResolver(fn() => $user);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('Accept', 'application/json');
    app()->instance('request', $request);
    return $request;
}

$root = storage_path('app/local-storage-volume-'.Str::uuid());
config(['local-storage.root' => $root]);
$disk = Storage::build(['driver' => 'local', 'root' => $root, 'throw' => true]);
$controller = new LocalStorageController();
$suffix = Str::random(10);
DB::beginTransaction();
try {
    $admin = User::create(['name' => 'Volume admin', 'email' => "$suffix-admin@example.test", 'password' => Str::random(24)]);
    $permission = Permission::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $admin->permissions()->attach($permission->id);
    $users = [];
    for ($i = 0; $i < 5; $i++) $users[] = User::create(['name' => "Volume user $i", 'email' => "$suffix-$i@example.test", 'password' => Str::random(24)]);
    $allIds = [];
    foreach ($users as $index => $user) {
        for ($i = 0; $i < 200; $i++) {
            $content = "Owner $index, document $i\n";
            $key = (string) Str::uuid();
            $response = $controller->upload(requestAs($user, ['upload_key' => $key], UploadedFile::fake()->createWithContent('daily-report.txt', $content)));
            $doc = StoredDocument::findOrFail($response->getData(true)['document']['id']);
            if ($response->getStatusCode() !== 201 || $disk->get($doc->path) !== $content || $doc->sha256 !== hash('sha256', $content)) throw new RuntimeException('Upload verification failed');
            $allIds[] = $doc->id;
            if ($i === 0) {
                $replay = $controller->upload(requestAs($user, ['upload_key' => $key], UploadedFile::fake()->createWithContent('daily-report.txt', $content)));
                if ($replay->getData(true)['document']['id'] !== $doc->id) throw new RuntimeException('Duplicate on replay');
            }
        }
        ensure(StoredDocument::where('owner_id', $user->id)->count() === 200, 'User '.($index + 1).': 200 verified files, repeated upload receipt creates no duplicate');
    }
    ensure(count($disk->allFiles()) === 1000, '1,000 physical files retained, even with identical original names');
    $date = now('Asia/Kolkata')->format('Y-m-d');
    $snapshot = max($allIds);
    $seen = [];
    $cursor = null;
    $pages = 0;
    do {
        $data = $controller->index(requestAs($admin, ['date' => $date, 'snapshot' => $snapshot, 'per_page' => 50, 'cursor' => $cursor]))->getData();
        foreach ($data['files']->items() as $doc) if (in_array($doc->id, $allIds)) $seen[] = $doc->id;
        $cursor = $data['files']->nextCursor()?->encode();
        if (++$pages === 1) {
            // A new upload between pages must not shift or duplicate the existing set.
            $controller->upload(requestAs($users[0], ['upload_key' => (string) Str::uuid()], UploadedFile::fake()->createWithContent('late-arrival.txt', 'New arrival')));
        }
        if ($pages > 1000) throw new RuntimeException('Pagination loop');
    } while ($cursor);
    sort($seen); sort($allIds);
    ensure($seen === $allIds && count(array_unique($seen)) === 1000, 'Stable pagination shows all 1,000 files once while new files arrive');
    $filtered = $controller->index(requestAs($admin, ['date' => $date, 'owner' => $users[1]->id, 'q' => 'daily-report', 'type' => 'other', 'per_page' => 200]))->getData();
    ensure($filtered['matchingFiles'] === 200 && count($filtered['files']) === 200, 'Date, user, name and type filters return exactly 200 matching files');
    $own = $controller->index(requestAs($users[1], ['owner' => $users[0]->id, 'per_page' => 200]))->getData();
    ensure($own['matchingFiles'] === 200 && collect($own['files']->items())->every(fn($doc) => $doc->owner_id === $users[1]->id), 'Regular user cannot expose other users through filter parameters');
    ensure((int) $filtered['dailyCounts'][$users[1]->id]->file_count === 200, 'Daily user register reconciles the uploaded count');
    $export = $controller->inventory(requestAs($admin, ['owner' => $users[1]->id, 'date' => $date]));
    ob_start(); $export->sendContent(); $csv = ob_get_clean();
    $csvLines = array_values(array_filter(explode("\n", trim($csv))));
    ensure(count($csvLines) === 201, 'CSV inventory includes all 200 files plus the header');
    $first = StoredDocument::findOrFail($allIds[0]);
    $receipt = $controller->uploadStatus(requestAs($users[0], ['keys' => [$first->upload_key]]))->getData(true)['documents'];
    ensure(count($receipt) === 1 && $receipt[0]['available'], 'Interrupted batch can reconcile server receipt');
    $receipt = $controller->uploadStatus(requestAs($users[1], ['keys' => [$first->upload_key]]))->getData(true)['documents'];
    ensure(count($receipt) === 0, 'Upload receipt lookup is restricted to its owner');
    try {
        $controller->upload(requestAs($users[0], ['upload_key' => $first->upload_key], UploadedFile::fake()->createWithContent('daily-report.txt', 'Different bytes')));
        throw new RuntimeException('Changed upload bytes accepted');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        ensure($e->getStatusCode() === 409, 'Reusing a receipt for different content is rejected');
    }
    echo "All 5-user / 1,000-file volume checks passed.\n";
} finally {
    DB::rollBack();
    if (str_starts_with($root, storage_path('app/local-storage-volume-'))) \Illuminate\Support\Facades\File::deleteDirectory($root);
}
