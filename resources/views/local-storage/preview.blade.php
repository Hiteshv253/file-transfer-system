@extends('local-storage.layout')
@section('content')
<div class="preview-heading"><a class="back-link" href="/files">← {{ __('Back to files') }}</a><div><h1>{{ $document->name }}</h1><p class="muted">{{ $document->owner?->name }} · {{ $document->folder_date }} · {{ number_format($document->size / 1024, 1) }} KB</p></div>@if(auth()->user()->portalCan('download'))<a class="button" href="/files/{{ $document->id }}/download">{{ __('Download') }}</a>@endif</div>
<section class="preview-stage" aria-label="{{ __('File preview') }}">
    @if($kind === 'image')<img class="full-preview" src="/files/{{ $document->id }}/download?preview=1" alt="{{ $document->name }}">
    @elseif($kind === 'video')<video controls preload="metadata" src="/files/{{ $document->id }}/download?preview=1">{{ __('Your browser cannot play this file.') }}</video>
    @elseif($kind === 'audio')<div class="audio-preview"><span aria-hidden="true">♫</span><audio controls preload="metadata" src="/files/{{ $document->id }}/download?preview=1">{{ __('Your browser cannot play this file.') }}</audio></div>
    @elseif(in_array($kind, ['pdf', 'text']))<iframe title="{{ __('File preview') }}" src="/files/{{ $document->id }}/download?preview=1"></iframe>
    @else<div class="unsupported-preview"><span aria-hidden="true">▤</span><h2>{{ __('Preview is not available for this file type.') }}</h2><p>{{ $document->mime }}</p><p>{{ __('Download this file to open it in a compatible application.') }}</p></div>@endif
</section>
@if($kind === 'text')<p class="muted">{{ __('Text previews show the first 1 MB.') }}</p>@endif
@endsection
