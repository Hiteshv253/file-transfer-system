<section class="card">
    <div class="section-heading"><div><h2>{{ __('Team accounts') }}</h2><p class="muted">{{ __('Choose exactly what each user can see and do.') }}</p></div><span class="badge">{{ $users->filter(fn($person) => !$person->hasPermission('admin'))->count() }} / {{ config('local-storage.user_limit') }}</span></div>
    <details class="create-account"><summary class="button">+ {{ __('Create a user account') }}</summary>
    <form action="/files/users" method="post">@csrf
        <div class="split"><div><label>{{ __('Name') }}<input name="name" required maxlength="100" autocomplete="off"></label><label>{{ __('Email') }}<input type="email" name="email" required autocomplete="off"></label></div><div><label>{{ __('Password') }}<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><label>{{ __('Confirm password') }}<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label></div></div>
        <fieldset class="permission-grid"><legend>{{ __('Permissions') }}</legend>@foreach(\App\Models\User::PORTAL_PERMISSIONS as $key => $label)<label><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, \App\Models\User::PORTAL_DEFAULTS))><span>{{ __($label) }}</span></label>@endforeach</fieldset>
        <p class="muted permission-help">{{ __('View permissions control which files are accessible. Direct deletion permanently removes accessible files.') }}</p><button>{{ __('Create user') }}</button>
    </form></details>
    <div class="user-cards">@foreach($users as $person)
        <article class="user-card"><div class="section-heading"><div class="person-heading"><span class="person-avatar">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span><div><strong>{{ $person->name }}</strong><small class="muted">{{ $person->email }}</small></div></div><span class="badge">{{ $person->hasPermission('admin') ? __('Super Admin') : __('User') }}</span></div>
        @if(!$person->hasPermission('admin'))
        <form method="post" action="/files/users/{{ $person->id }}/permissions">@csrf<fieldset class="permission-grid"><legend>{{ __('Permissions') }}</legend>@foreach(\App\Models\User::PORTAL_PERMISSIONS as $key => $label)<label><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $person->portalPermissions()))><span>{{ __($label) }}</span></label>@endforeach</fieldset><button>{{ __('Save permissions') }}</button></form>
        <form method="post" action="/files/users/{{ $person->id }}/delete" class="remove-account" data-confirm="{{ __('Remove this user? Access will stop immediately. Their files and audit history will be kept.') }}">@csrf<button class="danger-button">{{ __('Delete user') }}</button></form>
        @endif</article>
    @endforeach</div>
</section>
