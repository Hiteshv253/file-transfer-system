<?php

namespace App\Http\Controllers;

use App\Models\StoredDocument;
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
            return back()->withErrors(['email' => 'Email or password is incorrect.'])->onlyInput('email');
        }
        if (Auth::user()->banned_at) {
            Auth::logout();
            return back()->withErrors(['email' => 'This account is disabled.']);
        }
        $request->session()->regenerate();
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
        $activeTab = $request->input('tab', $request->filled('requests_page') ? 'requests' : 'files');
        if (!in_array($activeTab, $admin ? ['files', 'upload', 'requests', 'users'] : ['files', 'upload', 'requests'])) $activeTab = 'files';
        $base = StoredDocument::accessibleTo($user);
        $dates = (clone $base)->selectRaw('folder_date, COUNT(*) as file_count')->groupBy('folder_date')->orderByDesc('folder_date')->limit(90)->get();
        $snapshot = $request->filled('snapshot') ? (int) $request->snapshot : (int) (clone $base)->max('id');
        $filtered = $this->filteredDocuments($request)->where('id', '<=', $snapshot);
        $matchingFiles = (clone $filtered)->count();
        $files = (clone $filtered)->with('owner')->orderByDesc('id')
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
        return view('local-storage.index', compact('user', 'admin', 'files', 'dates', 'requests', 'pending', 'matchingFiles', 'snapshot', 'dailyCounts', 'summaryDate', 'availability', 'activeTab') + [
            'users' => User::orderBy('name')->get(),
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
        ]);
    }

    private function filteredDocuments(Request $request)
    {
        $query = StoredDocument::accessibleTo($request->user())
            ->when($request->date, fn($q) => $q->where('folder_date', $request->date))
            ->when($request->q, fn($q) => $q->where('name', 'like', '%'.$request->q.'%'));
        if ($request->user()->hasPermission('admin') && $request->filled('owner')) $query->where('owner_id', $request->owner);
        if ($request->type === 'pdf') $query->where('mime', 'application/pdf');
        elseif (in_array($request->type, ['image', 'audio', 'video'])) $query->where('mime', 'like', $request->type.'/%');
        elseif ($request->type === 'other') $query->where('mime', '!=', 'application/pdf')->where('mime', 'not like', 'image/%')->where('mime', 'not like', 'audio/%')->where('mime', 'not like', 'video/%');
        return $query;
    }

    public function inventory(Request $request)
    {
        $this->validateFilters($request);
        $query = $this->filteredDocuments($request)->with('owner');
        $query->where('id', '<=', $request->filled('snapshot') ? (int) $request->snapshot : (int) (clone $query)->max('id'));
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['File ID', 'Name', 'Owner', 'Folder date', 'Bytes', 'Uploaded at (UTC)', 'SHA-256']);
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
        $data = $request->validate(['keys' => 'required|array|max:1000', 'keys.*' => 'required|uuid']);
        $documents = StoredDocument::withTrashed()->where('owner_id', $request->user()->id)->whereIn('upload_key', $data['keys'])
            ->get(['id', 'upload_key', 'folder_date', 'deleted_at', 'path', 'size']);
        return response()->json(['documents' => $documents->map(fn($doc) => [
            'id' => $doc->id, 'upload_key' => $doc->upload_key, 'folder_date' => $doc->folder_date, 'deleted' => (bool) $doc->deleted_at,
            'available' => !$doc->deleted_at && $this->disk()->exists($doc->path) && $this->disk()->size($doc->path) === (int) $doc->size,
        ])]);
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|max:102400', 'upload_key' => 'nullable|uuid', 'share_with' => 'nullable|array', 'share_with.*' => 'integer|exists:users,id']);
        $file = $request->file('file');
        $date = now(config('local-storage.timezone'));
        $folder = $date->format('Y/m/Y-m-d');
        $path = $folder.'/'.Str::uuid();
        $hash = hash_file('sha256', $file->getRealPath());
        $written = false;
        try {
            $document = DB::transaction(function () use ($request, $file, $date, $path, $hash, &$written) {
                // Serialize commits for the same owner, including retries whose first
                // response was lost. A unique index also enforces one receipt per key.
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if ($request->filled('upload_key')) {
                    $existing = StoredDocument::withTrashed()->where('owner_id', $request->user()->id)->where('upload_key', $request->upload_key)->first();
                    if ($existing) {
                        abort_if($existing->trashed(), 409, 'This upload was already deleted with approval.');
                        abort_unless($existing->sha256 === $hash && (int) $existing->size === $file->getSize(), 409, 'Upload reference belongs to a different file.');
                        abort_unless($this->disk()->exists($existing->path), 409, 'Previously uploaded file is missing on disk. Contact the super admin.');
                        abort_unless($this->disk()->size($existing->path) === (int) $existing->size && hash_file('sha256', $this->disk()->path($existing->path)) === $existing->sha256, 409, 'Previously uploaded file failed integrity verification. Contact the super admin.');
                        return $existing;
                    }
                }
                $stream = fopen($file->getRealPath(), 'rb');
                $written = true;
                try { $this->disk()->put($path, $stream); } finally { if (is_resource($stream)) fclose($stream); }
                abort_unless($this->disk()->size($path) === $file->getSize() && hash_file('sha256', $this->disk()->path($path)) === $hash, 500, 'Stored file verification failed. Please retry.');
                if (config('local-storage.sync_writes')) {
                    $sync = fopen($this->disk()->path($path), 'r+b');
                    abort_unless($sync, 500, 'Cannot open stored file for durable synchronization.');
                    try { abort_unless(fsync($sync), 500, 'File synchronization failed. Please retry.'); }
                    finally { fclose($sync); }
                }
                $document = StoredDocument::create(['owner_id' => $request->user()->id,
                    'name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                    'path' => $path, 'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(), 'folder_date' => $date->format('Y-m-d'),
                    'upload_key' => $request->input('upload_key'), 'sha256' => $hash]);
                foreach (array_unique($request->input('share_with', [])) as $id) {
                    DB::table('stored_document_shares')->insert(['document_id' => $document->id, 'user_id' => $id]);
                }
                return $document;
            });
        } catch (\Throwable $e) { if ($written) $this->disk()->delete($path); throw $e; }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'File uploaded.', 'document' => [
                'id' => $document->id, 'name' => $document->name, 'size' => $document->size,
                'folder_date' => $document->folder_date,
            ]], 201);
        }
        return back()->with('status', 'File uploaded to '.$folder.'.');
    }

    public function download(Request $request, int $id)
    {
        $document = StoredDocument::accessibleTo($request->user())->findOrFail($id);
        abort_unless($this->disk()->exists($document->path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if ($request->boolean('preview')) {
            abort_unless($document->mime === 'application/pdf', 415, 'Only PDF files can be previewed.');
            return response()->file($this->disk()->path($document->path), $headers + ['Content-Type' => 'application/pdf', 'Content-Security-Policy' => "frame-ancestors 'self'"]);
        }
        return response()->download($this->disk()->path($document->path), $document->name, $headers);
    }

    public function requestDeletion(Request $request, int $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($request, $id, $data) {
            $document = StoredDocument::accessibleTo($request->user())->lockForUpdate()->findOrFail($id);
            abort_unless($document->owner_id == $request->user()->id || $request->user()->hasPermission('admin'), 403);
            abort_if(DB::table('document_deletion_requests')->where('document_id', $id)->where('status', 'pending')->exists(), 409, 'A deletion request is already pending.');
            DB::table('document_deletion_requests')->insert(['document_id' => $id, 'requested_by' => $request->user()->id,
                'reason' => $data['reason'], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        });
        return redirect('/files?tab=requests')->with('status', 'Deletion requested. The file remains available until approval.');
    }

    public function review(Request $request, int $id)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($request, $id, $data) {
            $review = DB::table('document_deletion_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($review, 404);
            abort_unless($review->status === 'pending', 409, 'Request has already been reviewed.');
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
        return back()->with('status', 'Deletion request '.$data['decision'].'.');
    }

    public function createUser(Request $request)
    {
        abort_unless($request->user()->hasPermission('admin'), 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:200|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        DB::transaction(function () use ($data) {
            // Serialize account creation with a stable lock, including simultaneous requests.
            $users = User::orderBy('id')->lockForUpdate()->get();
            abort_if($users->filter(fn($u) => !$u->hasPermission('admin'))->count() >= config('local-storage.user_limit'), 422, 'Maximum 5 regular users allowed.');
            User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'email_verified_at' => now()]);
        });
        return back()->with('status', 'User account created.');
    }
}
