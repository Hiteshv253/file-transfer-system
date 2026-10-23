@extends('local-storage.layout')
@section('content')
<div class="drive-workspace">
<aside class="drive-sidebar">
    <div class="sidebar-label">{{ __('YOUR WORKSPACE') }}</div>
    <nav class="portal-tabs" role="tablist" aria-label="{{ __('File management') }}" data-portal-tabs data-active-tab="{{ $activeTab }}">
        @foreach(['files' => ['files', 'View files']] + ($user->portalCan('upload') ? ['upload' => ['upload', 'Upload']] : []) + ['requests' => ['requests', 'Delete requests']] + ($admin ? ['users' => ['users', 'Users']] : []) as $tab => [$icon, $label])
        <a id="tab-{{ $tab }}" role="tab" href="{{ url('/files').'?'.http_build_query(array_merge(request()->except(['tab', 'requests_page']), ['tab' => $tab])) }}" data-tab="{{ $tab }}" aria-controls="panel-{{ $tab }}" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}" tabindex="{{ $activeTab === $tab ? 0 : -1 }}"><span aria-hidden="true">@include('local-storage.icon', ['name' => $icon])</span>{{ __($label) }}</a>
        @endforeach
    </nav>
    <div class="sidebar-label folder-label">{{ __('Daily folders') }} <span>{{ $totalDays }}</span></div>
    <nav class="folders date-folders" id="daily-folders" aria-label="{{ __('Daily folders') }}">
        <a href="/files?tab=files" class="{{ !request('date') ? 'active' : '' }}"><span class="folder-icon" aria-hidden="true">@include('local-storage.icon', ['name' => 'folder'])</span><span>{{ __('All dates') }}</span><small>{{ $totalFiles }}</small></a>
        @forelse($dates as $day)
        @php($date = $day->folder_date)
        <a href="/files?tab=files&amp;date={{ $date }}" class="{{ request('date') === $date ? 'active' : '' }}"><span class="folder-icon" aria-hidden="true">@include('local-storage.icon', ['name' => 'folder'])</span><span>{{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</span><small>{{ $day->file_count }}</small></a>
        @empty<p class="muted sidebar-empty">{{ __('No daily folders yet.') }}</p>@endforelse
    </nav>
    <div class="sidebar-footer"><span class="secure-dot"></span>{{ __('Private server storage') }}<small>{{ __('Files stay until you delete them.') }}</small></div>
</aside>
<div class="drive-main">
    <div class="top portal-heading"><div><span class="eyebrow">{{ __('YOUR FILES. ONE PLACE.') }}</span><h1>{{ $admin ? __('Team file library') : __('Your file library') }}</h1><p class="muted">{{ __('Private storage, organized by date.') }}</p></div><span class="today-chip">{{ now(config('local-storage.timezone'))->translatedFormat('d M Y') }}</span></div>
    <div class="compact-summary" id="library-stats" aria-label="{{ __('Storage summary') }}">
        <div><span class="stat-icon blue" aria-hidden="true">@include('local-storage.icon', ['name' => 'files'])</span><span><strong>{{ number_format($totalFiles) }}</strong><small>{{ $admin ? __('team files') : __('accessible files') }}</small></span></div>
        <div><span class="stat-icon purple" aria-hidden="true">@include('local-storage.icon', ['name' => 'storage'])</span><span><strong>{{ number_format($totalBytes / 1048576, 2) }} <em>MB</em></strong><small>{{ __('Storage used') }}</small></span></div>
        <div><span class="stat-icon amber" aria-hidden="true">@include('local-storage.icon', ['name' => 'folder'])</span><span><strong>{{ $totalDays }}</strong><small>{{ __('daily folders') }}</small></span></div>
    </div>
    <div class="tab-panel" id="panel-files" role="tabpanel" aria-labelledby="tab-files" data-portal-panel="files" @if($activeTab !== 'files') hidden @endif>@include('local-storage.listing')</div>
    @if($user->portalCan('upload'))<div class="tab-panel" id="panel-upload" role="tabpanel" aria-labelledby="tab-upload" data-portal-panel="upload" @if($activeTab !== 'upload') hidden @endif>@include('local-storage.upload')</div>@endif
    <div class="tab-panel" id="panel-requests" role="tabpanel" aria-labelledby="tab-requests" data-portal-panel="requests" @if($activeTab !== 'requests') hidden @endif>@include('local-storage.requests')</div>
    @if($admin)<div class="tab-panel" id="panel-users" role="tabpanel" aria-labelledby="tab-users" data-portal-panel="users" @if($activeTab !== 'users') hidden @endif>@include('local-storage.users')</div>@endif
</div>
</div>
@if($user->portalCan('upload'))<aside id="background-upload" class="background-upload" hidden aria-live="polite"><div><strong>{{ __('Uploading in background') }}</strong><small id="background-upload-summary"></small><progress id="background-upload-progress" max="100" value="0"></progress></div><button id="background-upload-open" type="button">{{ __('View progress') }}</button></aside>@endif
<script src="/js/local-storage-tabs.js?v={{ filemtime(public_path('js/local-storage-tabs.js')) }}" defer></script>
<script src="/js/local-storage-library.js?v={{ filemtime(public_path('js/local-storage-library.js')) }}" defer></script>
<script src="/js/local-storage-navigation.js?v={{ filemtime(public_path('js/local-storage-navigation.js')) }}" defer></script>
@endsection
