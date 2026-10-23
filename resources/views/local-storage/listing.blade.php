<section class="card" id="file-library" data-view="grid">
    <div class="top"><h2>{{ request('date') ?: __('All dates') }} <span class="muted">{{ __('/ Files') }}</span></h2>
        @if($user->portalCan('download'))<a href="{{ url('/files/inventory').'?'.http_build_query(array_merge(request()->except(['cursor', 'requests_page']), ['snapshot' => $snapshot])) }}">{{ __('Download inventory CSV') }}</a>@endif<div class="view-switch" aria-label="{{ __('View mode') }}"><button type="button" data-library-view="grid" aria-pressed="true" title="{{ __('Grid view') }}">▦ <span>{{ __('Grid') }}</span></button><button type="button" data-library-view="list" aria-pressed="false" title="{{ __('List view') }}">☷ <span>{{ __('List') }}</span></button></div>
    </div>
    <details class="library-filter-box" @if(request()->filled('date') || request()->filled('q') || request()->filled('owner') || request()->filled('type')) open @endif><summary>{{ __('Filter files') }} <small class="muted">· {{ __('Date, user, type & search') }}</small></summary>
    <form class="library-filters" method="get" action="/files#file-library">
        <input type="hidden" name="tab" value="files">
        <label>{{ __('Upload date') }}<input type="date" name="date" value="{{ request('date') }}"></label>
        <label>{{ __('Search file name') }}<input type="search" name="q" placeholder="{{ __('Search all matching files…') }}" value="{{ request('q') }}" maxlength="200"></label>
        @if($admin)<label>{{ __('Uploaded by') }}<select name="owner"><option value="">{{ __('All users') }}</option>@foreach($users as $person)<option value="{{ $person->id }}" @selected(request('owner') == $person->id)>{{ $person->name }}</option>@endforeach</select></label>@endif
        <label>{{ __('File type') }}<select name="type"><option value="">{{ __('All types') }}</option>@foreach(['pdf' => 'PDF', 'image' => 'Images', 'audio' => 'Audio', 'video' => 'Video', 'other' => 'Other files'] as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ __($label) }}</option>@endforeach</select></label>
        <label>{{ __('Files per page') }}<select name="per_page">@foreach([50, 100, 200] as $count)<option value="{{ $count }}" @selected((int) request('per_page', 50) === $count)>{{ $count }}</option>@endforeach</select></label>
        <div class="actions"><button>{{ __('Apply filters') }}</button><a href="/files#file-library">{{ __('Reset') }}</a></div>
    </form>
    </details>
    <div class="library-count"><strong>{{ number_format($matchingFiles) }} {{ __('matching files') }}</strong><span class="muted">{{ __('Showing') }} {{ count($files) }}. <a href="{{ url('/files').'?'.http_build_query(array_merge(request()->except(['cursor', 'snapshot', 'requests_page', 'tab']), ['tab' => 'files'])) }}#file-library">{{ __('Refresh') }}</a></span></div>
    <div class="file-gallery">
    @forelse($files as $file)
        @php($isImage = in_array($file->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp']))
        <article class="file-card">
            <div class="file-thumbnail">
                @if($user->portalCan('preview') && $availability[$file->id])<a href="/files/{{ $file->id }}/preview" aria-label="{{ __('Preview') }}: {{ $file->name }}">@endif
                @if($isImage && $user->portalCan('preview') && $availability[$file->id])<img src="/files/{{ $file->id }}/download?preview=1" alt="{{ $file->name }}" loading="lazy" decoding="async">
                @else<span class="file-type-icon {{ $file->mime === 'application/pdf' ? 'pdf-icon' : '' }}" aria-hidden="true">{{ $file->mime === 'application/pdf' ? 'PDF' : (str_starts_with($file->mime, 'video/') ? '▶' : (str_starts_with($file->mime, 'audio/') ? '♫' : '▤')) }}</span>@endif
                @if($user->portalCan('preview') && $availability[$file->id])<span class="preview-overlay">{{ __('Open preview') }} ↗</span></a>@endif
            </div>
            <div class="file-card-body"><strong class="file-name" title="{{ $file->name }}">{{ $file->name }}</strong><p class="file-meta">{{ $file->owner?->name ?? __('Deleted user') }} · {{ number_format($file->size / 1024, 1) }} KB</p><p class="file-meta">{{ $file->folder_date }} <span>#{{ $file->id }}</span></p>
            @if(!$availability[$file->id])<span class="badge approved">{{ __('Storage issue — contact super admin') }}</span>@endif
            @if(in_array($file->id, $pending))<span class="badge pending">{{ __('Deletion pending') }}</span>@endif
            @include('local-storage.file-actions')
            </div>
        </article>
    @empty<div class="empty-library"><span aria-hidden="true">▰</span><h2>{{ __('No files here yet') }}</h2><p>{{ __('Upload files or choose another date to get started.') }}</p></div>@endforelse
    </div>
    <div class="table-scroll library-scroll" tabindex="0" aria-label="{{ __('Scrollable file list') }}"><table class="responsive-table file-table" aria-label="{{ __('File library') }}"><thead><tr><th scope="col">{{ __('File / Reference') }}</th><th scope="col">{{ __('Uploaded by') }}</th><th scope="col">{{ __('Upload date / Size') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead><tbody>
    @forelse($files as $file)
        <tr><td><strong style="overflow-wrap:anywhere">{{ $file->name }}</strong><br><small class="muted">#{{ $file->id }} · {{ $file->mime }}</small>@if(!$availability[$file->id])<br><span class="badge approved">{{ __('Storage issue — contact super admin') }}</span>@endif @if(in_array($file->id, $pending))<br><span class="badge pending">{{ __('Deletion pending') }}</span>@endif</td>
        <td data-label="{{ __('Uploaded by') }}">{{ $file->owner?->name ?? __('Deleted user') }}@if($file->owner_id != $user->id)<br><small class="muted">{{ $admin ? __('Team file') : __('Shared with you') }}</small>@endif</td>
        <td data-label="{{ __('Upload date / Size') }}">{{ $file->folder_date }}<br><small class="muted">{{ $file->created_at->timezone(config('local-storage.timezone'))->format('h:i:s A') }} IST<br>{{ number_format($file->size / 1024, 1) }} KB</small></td>
        <td data-label="{{ __('Actions') }}">@include('local-storage.file-actions')</td></tr>
    @empty<tr><td colspan="4"><p class="muted">{{ __('No files match these filters. Choose another date or reset the filters.') }}</p></td></tr>@endforelse
    </tbody></table></div>
    <nav class="actions" style="margin-top:18px" aria-label="{{ __('File pages') }}">
        @if($files->previousPageUrl())<a class="button" href="{{ $files->previousPageUrl() }}#file-library">{{ __('← Previous files') }}</a>@endif
        @if($files->nextPageUrl())<a class="button" href="{{ $files->nextPageUrl() }}#file-library">{{ __('Next files →') }}</a>@endif
    </nav>
    <p class="muted"><small>{{ __('Files have unique references, so identical names never overwrite one another. New uploads appear after refresh; browsing pages keeps a stable set of files.') }}</small></p>
</section>
@if($admin)
<details class="card daily-register-details"><summary>{{ __('Daily user summary') }} · {{ $summaryDate }}</summary><section id="daily-upload-register"><h2>{{ __('Daily upload register') }} · {{ $summaryDate }}</h2><p class="muted">{{ __('Active files uploaded on this date. Compare these totals with each user’s batch report or the inventory CSV.') }}</p><div class="table-scroll"><table class="responsive-table register-table" aria-label="{{ __('Daily upload register') }}"><thead><tr><th>{{ __('User') }}</th><th>{{ __('Files uploaded') }}</th><th>{{ __('Storage') }}</th></tr></thead><tbody>@foreach($users as $person)<tr><td>{{ $person->name }}</td><td data-label="{{ __('Files uploaded') }}"><a href="/files?date={{ $summaryDate }}&amp;owner={{ $person->id }}#file-library">{{ number_format($dailyCounts->get($person->id)?->file_count ?? 0) }} {{ __('files') }}</a></td><td data-label="{{ __('Storage') }}">{{ number_format(($dailyCounts->get($person->id)?->bytes ?? 0) / 1048576, 2) }} MB</td></tr>@endforeach</tbody></table></div></section></details>
@endif
<style>.library-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:20px}.library-filters label{flex:1;min-width:135px;margin:0}.library-filters input,.library-filters select{margin-top:6px}.library-count{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:18px 0}.folders{max-height:450px;overflow:auto}</style>
