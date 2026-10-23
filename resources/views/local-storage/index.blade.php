@extends('local-storage.layout')
@section('content')
<div class="top portal-heading"><div><h1>{{ $admin ? 'Team file library' : 'Your file library' }}</h1><span class="muted">Private storage, organized by date.</span></div><span class="badge">LOCAL SERVER · {{ now(config('local-storage.timezone'))->format('d M Y') }}</span></div>
<div class="compact-summary" id="library-stats" aria-label="Storage summary">
    <span><strong>{{ number_format($totalFiles) }}</strong> {{ $admin ? 'team files' : 'accessible files' }}</span>
    <span><strong>{{ number_format($totalBytes / 1048576, 2) }} MB</strong> used</span>
    <span><strong>{{ $totalDays }}</strong> daily folders</span>
    <span class="muted">No auto expiry</span>
</div>
<nav class="portal-tabs" role="tablist" aria-label="File management" data-portal-tabs data-active-tab="{{ $activeTab }}">
    @foreach(['files' => 'View files', 'upload' => 'Upload', 'requests' => 'Delete requests'] + ($admin ? ['users' => 'Users'] : []) as $tab => $label)
    <a id="tab-{{ $tab }}" role="tab" href="{{ url('/files').'?'.http_build_query(array_merge(request()->except(['tab', 'requests_page']), ['tab' => $tab])) }}" data-tab="{{ $tab }}" aria-controls="panel-{{ $tab }}" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}" tabindex="{{ $activeTab === $tab ? 0 : -1 }}">{{ $label }}</a>
    @endforeach
</nav>
<div class="tab-panel" id="panel-files" role="tabpanel" aria-labelledby="tab-files" data-portal-panel="files" @if($activeTab !== 'files') hidden @endif>
    <details class="folder-picker"><summary>Daily folders <small class="muted">· {{ request('date') ?: 'All dates' }}</small></summary>
        <nav class="folders" id="daily-folders"><a href="/files?tab=files" class="{{ !request('date') ? 'active' : '' }}">All dates</a>@forelse($dates as $day)@php($date = $day->folder_date)<a href="/files?tab=files&amp;date={{ $date }}" class="{{ request('date') === $date ? 'active' : '' }}">{{ $date }} ({{ $day->file_count }})</a>@empty<span class="muted">No daily folders yet.</span>@endforelse</nav>
        <small class="muted">Latest 90 dates. Open any earlier date using the file filter.</small>
    </details>
    @include('local-storage.listing')
</div>
<div class="tab-panel" id="panel-upload" role="tabpanel" aria-labelledby="tab-upload" data-portal-panel="upload" @if($activeTab !== 'upload') hidden @endif>
    @include('local-storage.upload')
</div>
<div class="tab-panel" id="panel-requests" role="tabpanel" aria-labelledby="tab-requests" data-portal-panel="requests" @if($activeTab !== 'requests') hidden @endif>
<section class="card"><h2>{{ $admin ? 'Deletion approvals & audit history' : 'Your deletion requests' }}</h2><div class="table-scroll request-scroll" tabindex="0" aria-label="Scrollable deletion requests"><table class="responsive-table request-table" aria-label="Deletion requests"><thead><tr><th>File / Requested by</th><th>Reason</th><th>Status</th><th>Review</th></tr></thead><tbody>@forelse($requests as $review)<tr><td><strong>{{ $review->file_name }}</strong><br><small class="muted">{{ $review->requester }} · {{ $review->created_at }}</small></td><td data-label="Reason">{{ $review->reason }}</td><td data-label="Status"><span class="badge {{ $review->status }}">{{ ucfirst($review->status) }}</span></td><td data-label="Review">@if($admin && $review->status === 'pending')<form method="post" action="/files/requests/{{ $review->id }}/review">@csrf<input name="review_note" placeholder="Review note (optional)" aria-label="Review note" maxlength="2000"><div class="actions" style="margin-top:8px"><button name="decision" value="approved" style="background:#b02b42">Approve deletion</button><button name="decision" value="rejected" style="background:#52657f">Reject</button></div></form>@else{{ $review->review_note ?: '—' }}@if($review->reviewed_at)<br><small class="muted">{{ $review->reviewed_at }} · Admin #{{ $review->reviewed_by }}</small>@endif @endif</td></tr>@empty<tr><td colspan="4" class="muted">No deletion requests.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $requests->appends(['tab' => 'requests'])->links() }}</div></section>

</div>
@if($admin)<div class="tab-panel" id="panel-users" role="tabpanel" aria-labelledby="tab-users" data-portal-panel="users" @if($activeTab !== 'users') hidden @endif><section class="card"><h2>Create a user account</h2><p class="muted">Up to 5 regular users. Super admins are managed separately.</p><form action="/files/users" method="post">@csrf<div class="split"><div><label>Name<input name="name" required maxlength="100" autocomplete="off"></label><label>Email<input type="email" name="email" required autocomplete="off"></label></div><div><label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><label>Confirm password<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label><small class="muted">At least 10 characters, including letters and numbers.</small>
</div></div><button style="margin-top:16px">Create user</button></form><h2 style="margin-top:24px">Team accounts</h2>@foreach($users as $person)<p class="team-account">{{ $person->name }} <span class="muted">{{ $person->email }}</span> <span class="badge">{{ $person->hasPermission('admin') ? 'Super Admin' : 'User' }}</span></p>@endforeach</section></div>@endif
<details class="portal-help"><summary>Access &amp; storage policy</summary><p class="muted">{{ $admin ? 'You can browse every user’s files and review deletion requests.' : 'Only your own files and files shared with you appear here.' }} Files do not expire automatically. Only the super admin can approve deletion.</p></details>
<script src="/js/local-storage-tabs.js?v={{ filemtime(public_path('js/local-storage-tabs.js')) }}" defer></script>
@endsection