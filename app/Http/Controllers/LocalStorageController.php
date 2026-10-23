<?php

namespace App\Http\Controllers;

use App\Models\StoredDocument;
use App\Models\StoredFolder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Pagination\Cursor;

class LocalStorageController
{
    private function disk()
    {
        return Storage::build(['driver' => 'local', 'root' => config('local-storage.root'), 'throw' => true]);
    }

    public function loginPage()
    {
        return Auth::check() ? redirect('/files') : view('local-storage.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => __('Email or password is incorrect.')])->onlyInput('email');
        }
        if (Auth::user()->banned_at || Auth::user()->portal_deleted_at) {
            Auth::logout();
            return back()->withErrors(['email' => __('This account is disabled.')]);
        }
        $request->session()->regenerate();
        $request->session()->put('portal_session_version', (int) Auth::user()->portal_session_version);
        return redirect('/files');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/files/login');
    }

    public function index(Request $request)
    {
        $this->validateFilters($request);
        $user = $request->user();
        $admin = $user->hasPermission('admin');
        $currentFolder = $request->filled('folder') ? StoredFolder::accessibleTo($user)->findOrFail($request->integer('folder')) : null;
        $breadcrumbs = collect();
        for ($folder = $currentFolder; $folder; $folder = $folder->parent_id ? StoredFolder::find($folder->parent_id) : null) $breadcrumbs->prepend($folder);
        $folderQuery = StoredFolder::accessibleTo($user)->with('owner');
        if ($request->filled('q')) {
            $folderQuery->where('name', 'like', '%'.$request->q.'%');
            if ($currentFolder) $folderQuery->where('tree_path', 'like', $currentFolder->tree_path.'%')->where('id', '!=', $currentFolder->id);
        } else $folderQuery->where('parent_id', $currentFolder?->id ?? 0);
        $folders = $folderQuery->orderBy('name')->paginate(60, ['*'], 'folders_page')->withQueryString();
        $activeTab = $request->input('tab', $request->filled('requests_page') ? 'requests' : 'files');
        $tabs = ['files', 'requests'];
        if ($user->portalCan('upload')) $tabs[] = 'upload';
        if ($admin) $tabs[] = 'users';
        if (!in_array($activeTab, $tabs)) $activeTab = 'files';
        $base = StoredDocument::accessibleTo($user);
        $dates = (clone $base)->selectRaw('folder_date, COUNT(*) as file_count')->groupBy('folder_date')->orderByDesc('folder_date')->limit(90)->get();
        $snapshot = $request->filled('snapshot') ? (int) $request->snapshot : (int) (clone $base)->max('id');
        $filtered = $this->filteredDocuments($request)->where('id', '<=', $snapshot);
        $matchingFiles = (clone $filtered)->count();
        $files = (clone $filtered)->with(['owner', 'folder'])->orderByDesc('id')
            ->cursorPaginate((int) ($request->input('per_page') ?: 50), ['*'], 'cursor', Cursor::fromEncoded($request->input('cursor')))
            ->appends(array_merge($request->except(['cursor', 'requests_page', 'snapshot', 'tab']), ['snapshot' => $snapshot, 'tab' => 'files']));
        $availability = [];
        foreach ($files->items() as $document) {
            $availability[$document->id] = $this->disk()->exists($document->path) && $this->disk()->size($document->path) === (int) $document->size;
        }
        $summaryDate = $request->date ?: now(config('local-storage.timezone'))->format('Y-m-d');
        $dailyCounts = (clone $base)->where('folder_date', $summaryDate)->selectRaw('owner_id, COUNT(*) as file_count, SUM(size) as bytes')->groupBy('owner_id')->get()->keyBy('owner_id');
        $requests = DB::table('document_deletion_requests as r')->join('stored_documents as d', 'd.id', '=', 'r.document_id')
            ->join('users as u', 'u.id', '=', 'r.requested_by')
            ->when(!$admin, fn($q) => $q->where('r.requested_by', $user->id))
            ->select('r.*', 'd.name as file_name', 'u.name as requester')->orderByDesc('r.id')->paginate(20, ['*'], 'requests_page');
        $pending = DB::table('document_deletion_requests')->whereIn('document_id', collect($files->items())->pluck('id'))->where('status', 'pending')->pluck('document_id')->all();
        return view('local-storage.index', compact('user', 'admin', 'files', 'dates', 'requests', 'pending', 'matchingFiles', 'snapshot', 'dailyCounts', 'summaryDate', 'availability', 'activeTab', 'folders', 'currentFolder', 'breadcrumbs') + [
            'users' => User::whereNull('portal_deleted_at')->orderBy('name')->get(),
            'totalFiles' => (clone $base)->count(),
            'totalBytes' => (clone $base)->sum('size'),
            'totalDays' => (clone $base)->distinct()->count('folder_date'),
        ]);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d', 'q' => 'nullable|string|max:200',
            'owner' => 'nullable|integer|exists:users,id', 'type' => 'nullable|in:pdf,image,audio,video,other',
            'per_page' => 'nullable|in:50,100,200', 'snapshot' => 'nullable|integer|min:0', 'cursor' => 'nullable|string|max:500',
            'folder' => 'nullable|integer|min:1', 'folders_page' => 'nullable|integer|min:1',
        ]);
    }

    private function filteredDocuments(Request $request)
    {
        $query = StoredDocument::accessibleTo($request->user())
            ->when($request->date, fn($q) => $q->where('folder_date', $request->date))
            ->when($request->q, fn($q) => $q->where('name', 'like', '%'.$request->q.'%'));
        $folder = $request->filled('folder') ? StoredFolder::accessibleTo($request->user())->findOrFail($request->integer('folder')) : null;
        if ($request->filled('q')) {
            if ($folder) $query->whereIn('folder_id', StoredFolder::where('tree_path', 'like', $folder->tree_path.'%')->select('id'));
        } elseif ($folder) $query->where('folder_id', $folder->id);
        else $query->whereNull('folder_id');
        if ($request->user()->hasPermission('admin') && $request->filled('owner')) $query->where('owner_id', $request->owner);
        if ($request->type === 'pdf') $query->where('mime', 'application/pdf');
        elseif (in_array($request->type, ['image', 'audio', 'video'])) $query->where('mime', 'like', $request->type.'/%');
        elseif ($request->type === 'other') $query->where('mime', '!=', 'application/pdf')->where('mime', 'not like', 'image/%')->where('mime', 'not like', 'audio/%')->where('mime', 'not like', 'video/%');
        return $query;
    }

    public function inventory(Request $request)
    {
        abort_unless($request->user()->portalCan('download'), 403);
        $this->validateFilters($request);
        $query = $this->filteredDocuments($request)->with('owner');
        $query->where('id', '<=', $request->filled('snapshot') ? (int) $request->snapshot : (int) (clone $query)->max('id'));
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['File ID', __('Name'), 'Owner', 'Folder date', 'Bytes', 'Uploaded at (UTC)', 'SHA-256']);
            $query->chunkById(500, function ($documents) use ($out) {
                foreach ($documents as $doc) {
                    $row = [$doc->id, $doc->name, $doc->owner?->name, $doc->folder_date, $doc->size, $doc->created_at, $doc->sha256];
                    fputcsv($out, array_map(fn($value) => preg_match('/^[=+@\-\t\r\n]/', (string) $value) ? "'".$value : $value, $row));
                }
            });
            fclose($out);
        }, 'file-inventory.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
    }

    public function uploadStatus(Request $request)
    {
        abort_unless($request->user()->portalCan('upload'), 403);
        $data = $request->validate(['keys' => 'required|array|max:1000', 'keys.*' => 'required|uuid']);
        $documents = StoredDocument::withTrashed()->where('owner_id', $request->user()->id)->whereIn('upload_key', $data['keys'])
            ->get(['id', 'upload_key', 'folder_date', 'deleted_at', 'path', 'size', 'folder_id']);
        return response()->json(['documents' => $documents->map(fn($doc) => [
            'id' => $doc->id, 'upload_key' => $doc->upload_key, 'folder_date' => $doc->folder_date, 'folder_id' => $doc->folder_id, 'deleted' => (bool) $doc->deleted_at,
            'available' => !$doc->deleted_at && $this->disk()->exists($doc->path) && $this->disk()->size($doc->path) === (int) $doc->size,
        ])]);
    }

    public function upload(Request $request)
    {
        abort_unless($request->user()->portalCan('upload'), 403);
        $request->validate(['file' => 'required|file|max:102400', 'upload_key' => 'nullable|uuid', 'share_with' => 'nullable|array', 'share_with.*' => 'integer|exists:users,id',
            'parent_folder_id' => 'nullable|integer|min:0', 'relative_path' => 'nullable|string|max:2048']);
        $file = $request->file('file');
        $relativePath = $request->input('relative_path', '') ?: '';
        $segments = $relativePath !== '' ? $this->folderSegments($relativePath, true) : [];
        if ($segments) abort_unless(array_pop($segments) === $file->getClientOriginalName(), 422, __('Invalid folder path.'));
        $date = now(config('local-storage.timezone'));
        $folder = $date->format('Y/m/Y-m-d');
        $path = $folder.'/'.Str::uuid();
        $hash = hash_file('sha256', $file->getRealPath());
        $written = false;
        try {
            $document = DB::transaction(function () use ($request, $file, $date, $path, $hash, $segments, $relativePath, &$written) {
                // Serialize commits for the same owner, including retries whose first
                // response was lost. A unique index also enforces one receipt per key.
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if ($request->filled('upload_key')) {
                    $existing = StoredDocument::withTrashed()->where('owner_id', $request->user()->id)->where('upload_key', $request->upload_key)->first();
                    if ($existing) {
                        abort_if($existing->trashed(), 409, __('This upload was already deleted with approval.'));
                        abort_unless($existing->sha256 === $hash && (int) $existing->size === $file->getSize(), 409, __('Upload reference belongs to a different file.'));
                        abort_unless(($existing->relative_path ?: '') === $relativePath && (int) $existing->upload_parent_id === $request->integer('parent_folder_id'), 409, __('Upload reference belongs to a different folder.'));
                        abort_unless($this->disk()->exists($existing->path), 409, __('Previously uploaded file is missing on disk. Contact the super admin.'));
                        abort_unless($this->disk()->size($existing->path) === (int) $existing->size && hash_file('sha256', $this->disk()->path($existing->path)) === $existing->sha256, 409, __('Previously uploaded file failed integrity verification. Contact the super admin.'));
                        return $existing;
                    }
                }
                $targetFolder = $this->ensureFolders($request->user(), $request->integer('parent_folder_id'), $segments);
                $stream = fopen($file->getRealPath(), 'rb');
                $written = true;
                try { $this->disk()->put($path, $stream); } finally { if (is_resource($stream)) fclose($stream); }
                abort_unless($this->disk()->size($path) === $file->getSize() && hash_file('sha256', $this->disk()->path($path)) === $hash, 500, __('Stored file verification failed. Please retry.'));
                if (config('local-storage.sync_writes')) {
                    $sync = fopen($this->disk()->path($path), 'r+b');
                    abort_unless($sync, 500, __('Cannot open stored file for durable synchronization.'));
                    try { abort_unless(fsync($sync), 500, __('File synchronization failed. Please retry.')); }
                    finally { fclose($sync); }
                }
                $document = StoredDocument::create(['owner_id' => $request->user()->id,
                    'name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                    'path' => $path, 'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(), 'folder_date' => $date->format('Y-m-d'),
                    'upload_key' => $request->input('upload_key'), 'sha256' => $hash, 'folder_id' => $targetFolder?->id,
                    'upload_parent_id' => $request->integer('parent_folder_id') ?: null, 'relative_path' => $relativePath ?: null]);
                foreach (User::whereNull('portal_deleted_at')->whereIn('id', array_unique($request->input('share_with', [])))->pluck('id') as $id) {
                    DB::table('stored_document_shares')->insert(['document_id' => $document->id, 'user_id' => $id]);
                }
                return $document;
            });
        } catch (\Throwable $e) { if ($written) $this->disk()->delete($path); throw $e; }
        if ($request->expectsJson()) {
            return response()->json(['message' => __('File uploaded.'), 'document' => [
                'id' => $document->id, 'name' => $document->name, 'size' => $document->size,
                'folder_date' => $document->folder_date, 'folder_id' => $document->folder_id,
            ]], 201);
        }
        return back()->with('status', __('File uploaded to :folder.', ['folder' => $folder]));
    }

    public function download(Request $request, int $id)
    {
        abort_unless($request->user()->portalCan($request->boolean('preview') ? 'preview' : 'download'), 403);
        $document = StoredDocument::accessibleTo($request->user())->findOrFail($id);
        abort_unless($this->disk()->exists($document->path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if ($request->boolean('preview')) {
            $kind = $this->previewKind($document->mime);
            abort_if($kind === 'unsupported', 415);
            if ($kind === 'text') {
                $handle = $this->disk()->readStream($document->path);
                try { $text = stream_get_contents($handle, 1048576); }
                finally { fclose($handle); }
                return response($text, 200, $headers + ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Security-Policy' => "sandbox; default-src 'none'; frame-ancestors 'self'"]);
            }
            return response()->file($this->disk()->path($document->path), $headers + ['Content-Type' => $document->mime, 'Content-Security-Policy' => "frame-ancestors 'self'"]);
        }
        return response()->download($this->disk()->path($document->path), $document->name, $headers);
    }

    public function requestDeletion(Request $request, int $id)
    {
        abort_unless($request->user()->portalCan('request_delete'), 403);
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($request, $id, $data) {
            $document = StoredDocument::accessibleTo($request->user())->lockForUpdate()->findOrFail($id);
            abort_unless($document->owner_id == $request->user()->id || $request->user()->hasPermission('admin'), 403);
            abort_if(DB::table('document_deletion_requests')->where('document_id', $id)->where('status', 'pending')->exists(), 409, __('A deletion request is already pending.'));
            DB::table('document_deletion_requests')->insert(['document_id' => $id, 'requested_by' => $request->user()->id,
                'reason' => $data['reason'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        });
        return redirect('/files?tab=requests')->with('status', __('Deletion requested. The file remains available until approval.'));
    }

    public function review(Request $request, int $id)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($request, $id, $data) {
            $review = DB::table('document_deletion_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($review, 404);
            abort_unless($review->status === 'pending', 409, __('Request has already been reviewed.'));
            $document = StoredDocument::lockForUpdate()->findOrFail($review->document_id);
            if ($data['decision'] === 'approved') {
                // Keep the document metadata and request permanently for the audit trail.
                $this->disk()->delete($document->path);
                $document->delete();
            }
            DB::table('document_deletion_requests')->where('id', $id)->update([
                'status' => $data['decision'], 'reviewed_by' => $request->user()->id,
                'review_note' => $data['review_note'] ?? null, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('status', __($data['decision'] === 'approved' ? 'Deletion request approved.' : 'Deletion request rejected.'));
    }

    public function createUser(Request $request)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:200|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'permissions' => 'sometimes|array', 'permissions.*' => 'string|in:'.implode(',', array_keys(User::PORTAL_PERMISSIONS))]);
        DB::transaction(function () use ($data) {
            // Serialize account creation with a stable lock, including simultaneous requests.
            $users = User::whereNull('portal_deleted_at')->orderBy('id')->lockForUpdate()->get();
            abort_if($users->filter(fn($u) => !$u->hasPermission('admin'))->count() >= config('local-storage.user_limit'), 422, __('Maximum 5 regular users allowed.'));
            User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'email_verified_at' => now(),
                'portal_permissions' => json_encode(array_values(array_unique($data['permissions'] ?? []))) ]);
        });
        return back()->with('status', __('User account created.'));
    }

    public function updateUser(Request $request, int $id)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $data = $request->validate(['permissions' => 'sometimes|array',
            'permissions.*' => 'string|in:'.implode(',', array_keys(User::PORTAL_PERMISSIONS))]);
        DB::transaction(function () use ($id, $data) {
            $person = User::whereNull('portal_deleted_at')->lockForUpdate()->findOrFail($id);
            abort_if($person->hasPermission('admin'), 403);
            $person->portal_permissions = json_encode(array_values(array_unique($data['permissions'] ?? [])));
            $person->save();
        });
        return back()->with('status', __('User permissions updated.'));
    }

    public function resetUserPassword(Request $request, int $id)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        DB::transaction(function () use ($id, $data) {
            $person = User::whereNull('portal_deleted_at')->lockForUpdate()->findOrFail($id);
            abort_if($person->hasPermission('admin'), 403);
            $person->password = $data['password'];
            $person->remember_token = null;
            $person->portal_session_version = (int) $person->portal_session_version + 1;
            $person->save();
        });
        return back()->with('status', __('Password updated. The user must sign in again.'));
    }

    private function bulkIds(Request $request): array
    {
        // Grid and list checkboxes represent the same visible files.
        $data = $request->validate(['ids' => 'required|array|min:1|max:400', 'ids.*' => 'required|integer|min:1']);
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        abort_if(count($ids) > 200, 422, __('Select up to 200 items at a time.'));
        sort($ids);
        return $ids;
    }

    public function bulkDelete(Request $request)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $ids = $this->bulkIds($request);
        abort_unless(StoredDocument::accessibleTo($request->user())->whereIn('id', $ids)->count() === count($ids), 409, __('Selection changed. Refresh and select the items again.'));
        $done = 0;
        foreach ($ids as $id) {
            try { $this->deleteDocument($request, $id); $done++; }
            catch (\Throwable $error) { report($error); }
        }
        return back()->with('status', __('Deleted :done files. :failed failed.', ['done' => $done, 'failed' => count($ids) - $done]));
    }

    public function bulkReview(Request $request)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $ids = $this->bulkIds($request);
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:2000']);
        abort_unless(DB::table('document_deletion_requests')->whereIn('id', $ids)->where('status', 'pending')->count() === count($ids), 409, __('Selection changed. Refresh and select the items again.'));
        $done = 0;
        foreach ($ids as $id) {
            try { $this->review($request, $id); $done++; }
            catch (\Throwable $error) { report($error); }
        }
        return back()->with('status', __($data['decision'] === 'approved' ? 'Approved :done requests. :failed failed.' : 'Rejected :done requests. :failed failed.', ['done' => $done, 'failed' => count($ids) - $done]));
    }

    public function deleteUser(Request $request, int $id)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        DB::transaction(function () use ($id) {
            $person = User::whereNull('portal_deleted_at')->lockForUpdate()->findOrFail($id);
            abort_if($person->hasPermission('admin'), 403);
            // Retain ownership and audit references; deny existing sessions immediately.
            $person->forceFill(['portal_deleted_at' => now(), 'banned_at' => now(),
                'portal_permissions' => '[]', 'remember_token' => null])->save();
        });
        return back()->with('status', __('User removed. Their files and audit history are retained.'));
    }

    public function deleteDocument(Request $request, int $id)
    {
        abort_unless($request->user()->portalCan('delete'), 403);
        DB::transaction(function () use ($request, $id) {
            $document = StoredDocument::accessibleTo($request->user())->lockForUpdate()->findOrFail($id);
            $document->delete();
            DB::table('document_deletion_requests')->where('document_id', $id)->where('status', 'pending')
                ->update(['status' => 'approved', 'reviewed_by' => $request->user()->id,
                    'review_note' => 'Deleted directly by an authorized user.', 'reviewed_at' => now(), 'updated_at' => now()]);
            DB::table('document_deletion_requests')->insert(['document_id' => $id, 'requested_by' => $request->user()->id,
                'reason' => 'Direct deletion', 'status' => 'approved', 'reviewed_by' => $request->user()->id,
                'review_note' => 'Deleted directly by an authorized user.', 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            // Throwing disk errors roll back metadata; row locking serializes deletion.
            $this->disk()->delete($document->path);
        });
        return back()->with('status', __('File deleted.'));
    }

    private function previewKind(string $mime): string
    {
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'])) return 'image';
        if ($mime === 'application/pdf') return 'pdf';
        if (in_array($mime, ['video/mp4', 'video/webm', 'video/ogg'])) return 'video';
        if (in_array($mime, ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm', 'audio/mp4', 'audio/flac'])) return 'audio';
        if (str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/xml'])) return 'text';
        return 'unsupported';
    }

    public function preview(Request $request, int $id)
    {
        abort_unless($request->user()->portalCan('preview'), 403);
        $document = StoredDocument::accessibleTo($request->user())->with('owner')->findOrFail($id);
        abort_unless($this->disk()->exists($document->path), 404);
        $kind = $this->previewKind($document->mime);
        return view('local-storage.preview', compact('document', 'kind'));
    }

    private function folderSegments(string $path, bool $filePath = false): array
    {
        abort_if($path === '' || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/u', $path), 422, __('Invalid folder path.'));
        $segments = explode('/', $path);
        abort_if(count($segments) > ($filePath ? 31 : 30), 422, __('Folders can be nested up to 30 levels.'));
        foreach ($segments as $index => $segment) {
            $max = $filePath && $index === count($segments) - 1 ? 255 : 190;
            abort_if(trim($segment) === '' || in_array(trim($segment), ['.', '..']) || mb_strlen($segment) > $max, 422, __('Invalid folder path.'));
        }
        return $segments;
    }

    private function ensureFolders(User $user, int $parentId, array $segments): ?StoredFolder
    {
        $parent = $parentId ? StoredFolder::whereKey($parentId)->lockForUpdate()->firstOrFail() : null;
        if ($parent) abort_unless($parent->owner_id == $user->id || $user->hasPermission('admin'), 403);
        $depth = $parent ? count(array_filter(explode('/', $parent->tree_path))) : 0;
        abort_if($depth + count($segments) > 30, 422, __('Folders can be nested up to 30 levels.'));
        foreach ($segments as $name) {
            $folder = StoredFolder::withTrashed()->lockForUpdate()->firstOrCreate(['owner_id' => $parent?->owner_id ?? $user->id,
                'parent_id' => $parent?->id ?? 0, 'name' => $name]);
            if ($folder->trashed()) $folder->restore();
            if (!$folder->tree_path) { $folder->tree_path = ($parent?->tree_path ?? '/').$folder->id.'/'; $folder->save(); }
            $parent = $folder;
        }
        return $parent;
    }

    public function createFolder(Request $request)
    {
        abort_unless($request->user()->portalCan('upload'), 403);
        $data = $request->validate(['name' => 'required|string|max:190', 'parent_id' => 'nullable|integer|min:0']);
        $segments = $this->folderSegments($data['name']);
        abort_unless(count($segments) === 1, 422, __('Invalid folder path.'));
        $folder = DB::transaction(function () use ($request, $segments) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            return $this->ensureFolders($request->user(), $request->integer('parent_id'), $segments);
        });
        if ($request->expectsJson()) return response()->json(['folder' => $folder], 201);
        $destination = StoredFolder::accessibleTo($request->user())->whereKey($folder->id)->exists() ? '/files?folder='.$folder->id : '/files?tab=upload';
        return redirect($destination)->with('status', __('Folder created.'));
    }

    public function createFolderTree(Request $request)
    {
        abort_unless($request->user()->portalCan('upload'), 403);
        $data = $request->validate(['paths' => 'required|array|min:1|max:1000', 'paths.*' => 'required|string|max:2048', 'parent_id' => 'nullable|integer|min:0']);
        $paths = array_map(fn($path) => $this->folderSegments($path), array_unique($data['paths']));
        DB::transaction(function () use ($request, $paths) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            foreach ($paths as $segments) $this->ensureFolders($request->user(), $request->integer('parent_id'), $segments);
        });
        return response()->json(['message' => __('Folders created.')], 201);
    }

    public function deleteFolder(Request $request, int $id)
    {
        abort_unless($request->user()->portalCan('delete'), 403);
        DB::transaction(function () use ($request, $id) {
            $folder = StoredFolder::accessibleTo($request->user())->lockForUpdate()->findOrFail($id);
            abort_unless($folder->owner_id == $request->user()->id || $request->user()->hasPermission('admin'), 403);
            abort_if($folder->children()->exists() || $folder->documents()->exists(), 422, __('Only empty folders can be deleted.'));
            $folder->delete();
        });
        return back()->with('status', __('Folder deleted.'));
    }
}
