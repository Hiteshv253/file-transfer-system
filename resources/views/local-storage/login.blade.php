@extends('local-storage.layout')
@section('content')
<div class="login card"><span class="badge">{{ __('SECURE ACCESS') }}</span><h1 style="margin-top:18px">{{ __('Welcome to File Vault') }}</h1><p class="muted">{{ __('Sign in to upload, share and access your files.') }}</p><form method="post" action="/files/login">@csrf<label for="email">{{ __('Email address') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus><label for="password">{{ __('Password') }}</label><input id="password" type="password" name="password" required autocomplete="current-password"><button>{{ __('Sign in →') }}</button></form><p class="muted"><small>{{ __('Accounts are created by your super admin.') }}</small></p></div>
@endsection
