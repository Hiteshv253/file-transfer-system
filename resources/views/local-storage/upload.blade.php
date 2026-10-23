<section class="card" id="upload-card">
    <div class="upload-card-heading"><div><h2>{{ __('Upload files') }}</h2><p class="muted upload-background-help">{{ __('Add files or drop a whole folder. Keep everything organized.') }}</p></div>
    <p class="upload-destination muted">@include('local-storage.icon', ['name' => 'folder'])<span>{{ __('Upload destination') }}<strong id="upload-destination">{{ $currentFolder?->name ?? __('All files') }}</strong></span></p></div>
    <form id="upload-form" action="/files/upload" method="post" enctype="multipart/form-data" data-user-id="{{ $user->id }}" data-folder-id="{{ $currentFolder?->id ?? 0 }}">
        @csrf
        <div class="split upload-workspace">
            <div>
                <div id="file-dropzone" class="upload file-dropzone" role="button" tabindex="0" aria-controls="file" aria-label="{{ __('Browse files or drop files and folders here') }}">
                    <span class="upload-symbol" aria-hidden="true">@include('local-storage.icon', ['name' => 'upload'])</span>
                    <strong>{{ __('Drop files or folders here') }}</strong>
                    <small class="muted">{{ __('PDF, images, audio, video & all other file types') }}</small>
                    <span class="browse-files">{{ __('Browse files') }} <span aria-hidden="true">+</span></span>
                </div>
                <input id="file" type="file" multiple hidden>
                <input id="folder-input" type="file" multiple webkitdirectory directory hidden>
                <p id="upload-folder-summary" class="muted" aria-live="polite"></p>
                <p class="muted upload-limit"><small>{{ __('Up to 100 MB per file. Select multiple files together.') }}</small></p>
            </div>
            <div class="upload-sharing">
                <fieldset id="share_with" class="share-users">
                    <legend>{{ __('Share with users') }} <small class="muted">{{ __('(optional)') }}</small></legend>
                    <div class="share-options">
                    @forelse($users->where('id', '!=', $user->id) as $person)
                        <label class="share-person"><input type="checkbox" name="share_with[]" value="{{ $person->id }}"><span><strong>{{ $person->name }}</strong><small>{{ $person->email }}</small></span></label>
                    @empty<p class="muted">{{ __('No other users yet. You can upload privately.') }}</p>@endforelse
                    </div>
                </fieldset>
                <small class="muted">{{ __('Tap users to select them. Applies to all files in this upload.') }}</small>
            </div>
        </div>
        <p class="upload-background-note muted"><span class="secure-dot" aria-hidden="true"></span>{{ __('Uploads continue while you browse this website. Keep this browser tab open.') }}</p>
        <details id="upload-list-details" class="upload-list-details" hidden><summary id="upload-list-title">{{ __('Selected files') }}</summary><div id="upload-queue" class="upload-queue" aria-label="{{ __('Selected files') }}"></div></details>
        <div id="upload-overall" hidden>
            <div class="upload-summary"><strong id="upload-summary">{{ __('Ready to upload') }}</strong><span id="upload-percentage">0%</span></div>
            <progress id="upload-total-progress" max="100" value="0" aria-label="{{ __('Overall upload progress') }}"></progress>
        </div>
        <p id="upload-feedback" role="status" aria-live="polite" class="muted"></p>
        <div class="actions">
            <button id="upload-submit" type="submit" disabled>{{ __('Upload files') }}</button>
            <button id="upload-clear" type="button" class="secondary" hidden>{{ __('Clear list') }}</button>
            <a id="upload-view-files" class="button" href="/files?date={{ now(config('local-storage.timezone'))->format('Y-m-d') }}" hidden>{{ __('View uploaded files') }}</a>
            <button id="upload-report" type="button" class="secondary" hidden>{{ __('Download batch report') }}</button>
        </div>
        <details class="upload-help"><summary>{{ __('Upload recovery & reports') }}</summary><p class="muted"><small>{{ __('Every file gets a server receipt after verification. Interrupted batches are remembered on this browser; reselect the original files to continue. Keep a copy of the batch report to reconcile your daily uploads.') }}</small></p></details>
        <noscript><p>{{ __('Please enable JavaScript to use multiple uploads and upload progress.') }}</p></noscript>
    </form>
</section>
<style>
[hidden]{display:none!important}.file-dropzone{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;min-height:185px;text-align:center;cursor:pointer;transition:background .15s,border-color .15s}.file-dropzone:hover,.file-dropzone.drag-over,.file-dropzone:focus-visible{background:#edf3ff;border-color:#2459cc;outline:2px solid #2459cc;outline-offset:2px}.file-dropzone[aria-disabled="true"]{opacity:.65;cursor:wait}.upload-symbol{font-size:27px;color:#2459cc}.browse-files{color:#2459cc;text-decoration:underline}.upload-queue{max-height:350px;overflow:auto;margin-top:14px}.upload-item{padding:14px 0;border-bottom:1px solid #edf0f5}.upload-item-top,.upload-summary{display:flex;align-items:center;justify-content:space-between;gap:12px}.upload-item-name{overflow-wrap:anywhere;min-width:0}.upload-item-name strong{display:block}.upload-item small{display:block;color:#728098;margin-top:4px}.upload-item button{padding:5px 10px;font-size:12px;background:#edf2fa;color:#46556e}.upload-item[data-state="failed"] small,.upload-item[data-state="invalid"] small{color:#b02b42}.upload-item[data-state="done"] small{color:#19704a}progress{display:block;width:100%;height:8px;margin-top:10px;border:0;border-radius:10px;overflow:hidden;accent-color:#2459cc;background:#edf2fa}progress::-webkit-progress-bar{background:#edf2fa}progress::-webkit-progress-value{background:#2459cc;border-radius:10px}progress::-moz-progress-bar{background:#2459cc}#upload-overall{margin-top:20px}.secondary{background:#edf2fa;color:#46556e}button:disabled{opacity:.5;cursor:not-allowed}
</style>
<script src="/js/local-file-upload.js?v={{ filemtime(public_path('js/local-file-upload.js')) }}" defer></script>
