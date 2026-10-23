<section class="card" id="file-library">
    <div class="top"><h2>{{ request('date') ?: 'All dates' }} <span class="muted">/ Files</span></h2>
        <a href="{{ url('/files/inventory').'?'.http_build_query(array_merge(request()->except(['cursor', 'requests_page']), ['snapshot' => $snapshot])) }}">Download inventory CSV</a>
    </div>
    <details class="library-filter-box" @if(request()->filled('date') || request()->filled('q') || request()->filled('owner') || request()->filled('type')) open @endif><summary>Filter files <small class="muted">· Date, user, type &amp; search</small></summary>
    <form class="library-filters" method="get" action="/files#file-library">
        <input type="hidden" name="tab" value="files">
        <label>Upload date<input type="date" name="date" value="{{ request('date') }}"></label>
        <label>Search file name<input type="search" name="q" placeholder="Search all matching files…" value="{{ request('q') }}" maxlength="200"></label>
        @if($admin)<label>Uploaded by<select name="owner"><option value="">All users</option>@foreach($users as $person)<option value="{{ $person->id }}" @selected(request('owner') == $person->id)>{{ $person->name }}</option>@endforeach</select></label>@endif
        <label>File type<select name="type"><option value="">All types</option>@foreach(['pdf' => 'PDF', 'image' => 'Images', 'audio' => 'Audio', 'video' => 'Video', 'other' => 'Other files'] as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Files per page<select name="per_page">@foreach([50, 100, 200] as $count)<option value="{{ $count }}" @selected((int) request('per_page', 50) === $count)>{{ $count }}</option>@endforeach</select></label>
        <div class="actions"><button>Apply filters</button><a href="/files#file-library">Reset</a></div>
    </form>
    </details>
    <div class="library-count"><strong>{{ number_format($matchingFiles) }} matching files</strong><span class="muted">Showing {{ count($files) }}. <a href="{{ url('/files').'?'.http_build_query(array_merge(request()->except(['cursor', 'snapshot', 'requests_page', 'tab']), ['tab' => 'files'])) }}#file-library">Refresh</a></span></div>
    <div class="table-scroll library-scroll" tabindex="0" aria-label="Scrollable file list"><table class="responsive-table file-table" aria-label="File library"><thead><tr><th scope="col">File / Reference</th><th scope="col">Uploaded by</th><th scope="col">Upload date / Size</th><th scope="col">Actions</th></tr></thead><tbody>
    @forelse($files as $file)
        <tr><td><strong style="overflow-wrap:anywhere">{{ $file->name }}</strong><br><small class="muted">#{{ $file->id }} · {{ $file->mime }}</small>@if(!$availability[$file->id])<br><span class="badge approved">Storage issue — contact super admin</span>@endif @if(in_array($file->id, $pending))<br><span class="badge pending">Deletion pending</span>@endif</td>
        <td data-label="Uploaded by">{{ $file->owner?->name ?? 'Deleted user' }}@if($file->owner_id != $user->id)<br><small class="muted">{{ $admin ? 'Team file' : 'Shared with you' }}</small>@endif</td>
        <td data-label="Upload date / Size">{{ $file->folder_date }}<br><small class="muted">{{ $file->created_at->timezone(config('local-storage.timezone'))->format('h:i:s A') }} IST<br>{{ number_format($file->size / 1024, 1) }} KB</small></td>
        <td data-label="Actions"><div class="actions file-actions"><a href="/files/{{ $file->id }}/download">Download ↓</a>@if($file->mime === 'application/pdf')<a href="/files/{{ $file->id }}/download?preview=1" target="_blank" rel="noopener">View PDF ↗</a>@endif</div>
        @if(($admin || $file->owner_id == $user->id) && !in_array($file->id, $pending))<details style="margin-top:10px"><summary>Request deletion</summary><form method="post" action="/files/{{ $file->id }}/request-deletion">@csrf<label>Reason<textarea name="reason" required maxlength="2000" rows="2"></textarea></label><button>Send request</button></form></details>@endif</td></tr>
    @empty<tr><td colspan="4"><p class="muted">No files match these filters. Choose another date or reset the filters.</p></td></tr>@endforelse
    </tbody></table></div>
    <nav class="actions" style="margin-top:18px" aria-label="File pages">
        @if($files->previousPageUrl())<a class="button" href="{{ $files->previousPageUrl() }}#file-library">← Previous files</a>@endif
        @if($files->nextPageUrl())<a class="button" href="{{ $files->nextPageUrl() }}#file-library">Next files →</a>@endif
    </nav>
    <p class="muted"><small>Files have unique references, so identical names never overwrite one another. New uploads appear after refresh; browsing pages keeps a stable set of files.</small></p>
</section>
@if($admin)
<details class="card daily-register-details"><summary>Daily user summary · {{ $summaryDate }}</summary><section id="daily-upload-register"><h2>Daily upload register · {{ $summaryDate }}</h2><p class="muted">Active files uploaded on this date. Compare these totals with each user’s batch report or the inventory CSV.</p><div class="table-scroll"><table class="responsive-table register-table" aria-label="Daily upload register"><thead><tr><th>User</th><th>Files uploaded</th><th>Storage</th></tr></thead><tbody>@foreach($users as $person)<tr><td>{{ $person->name }}</td><td data-label="Files uploaded"><a href="/files?date={{ $summaryDate }}&amp;owner={{ $person->id }}#file-library">{{ number_format($dailyCounts->get($person->id)?->file_count ?? 0) }} files</a></td><td data-label="Storage">{{ number_format(($dailyCounts->get($person->id)?->bytes ?? 0) / 1048576, 2) }} MB</td></tr>@endforeach</tbody></table></div></section></details>
@endif
<style>.library-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:20px}.library-filters label{flex:1;min-width:135px;margin:0}.library-filters input,.library-filters select{margin-top:6px}.library-count{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:18px 0}.folders{max-height:450px;overflow:auto}</style>
