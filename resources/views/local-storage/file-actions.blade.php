<div class="actions file-actions">
    @if($user->portalCan('preview') && $availability[$file->id])<a class="preview-action" href="/files/{{ $file->id }}/preview">{{ __('Preview') }}</a>@endif
    @if($user->portalCan('download') && $availability[$file->id])<a href="/files/{{ $file->id }}/download">{{ __('Download') }}</a>@endif
    @if($user->portalCan('delete'))<form method="post" action="/files/{{ $file->id }}/delete" data-confirm="{{ __('Permanently delete this file? This cannot be undone.') }}">@csrf<button class="danger-button">{{ __('Delete') }}</button></form>@endif
</div>
@if($user->portalCan('request_delete') && !$user->portalCan('delete') && $file->owner_id == $user->id && !in_array($file->id, $pending))
<details class="request-delete"><summary>{{ __('Request deletion') }}</summary><form method="post" action="/files/{{ $file->id }}/request-deletion">@csrf<label>{{ __('Reason') }}<textarea name="reason" required maxlength="2000" rows="2"></textarea></label><button>{{ __('Send request') }}</button></form></details>
@endif
