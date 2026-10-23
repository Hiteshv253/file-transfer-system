<?php

// Explicit account management; normal startup never resets existing passwords.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$email = $argv[1] ?? '';
$password = rtrim(stream_get_contents(STDIN), "\r\n");
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Provide a valid email argument and a password of at least 12 characters on stdin.\n");
    exit(1);
}
\Illuminate\Support\Facades\DB::transaction(function () use ($email, $password) {
    $user = \App\Models\User::firstOrNew(['email' => $email]);
    if (!$user->exists) $user->name = 'Hitesh';
    $user->password = $password;
    $user->email_verified_at = now();
    $user->banned_at = null;
    $user->portal_deleted_at = null;
    $user->save();
    $permission = \Common\Auth\Permissions\Permission::firstOrCreate(
        ['name' => 'admin'], ['display_name' => 'Super Admin']
    );
    $user->permissions()->syncWithoutDetaching([$permission->id]);
});
echo "Super admin account updated.\n";
