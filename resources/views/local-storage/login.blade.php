@extends('local-storage.layout')
@section('content')
<div class="login card"><span class="badge">SECURE ACCESS</span><h1 style="margin-top:18px">Welcome to File Vault</h1><p class="muted">Sign in to upload, share and access your files.</p><form method="post" action="/files/login">@csrf<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"><button>Sign in →</button></form><p class="muted"><small>Accounts are created by your super admin.</small></p></div>
@endsection
