<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hasAdmin = \App\Models\User::all()->contains(fn($user) => $user->hasPermission('admin'));
if ($hasAdmin) { echo "Existing super admin retained. No passwords changed.\n"; exit(0); }
$email = getenv('BOOTSTRAP_ADMIN_EMAIL');
$password = getenv('BOOTSTRAP_ADMIN_PASSWORD');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password ?: '') < 12) {
    fwrite(STDERR, "Bootstrap admin email/password are missing or invalid.\n"); exit(1);
}
if (\App\Models\User::where('email', $email)->exists()) {
    fwrite(STDERR, "Bootstrap email belongs to an existing non-admin; refusing to elevate it.\n"); exit(1);
}
\Illuminate\Support\Facades\DB::transaction(function () use ($email, $password) {
    $user = \App\Models\User::create(['name' => 'Super Admin', 'email' => $email, 'password' => $password, 'email_verified_at' => now()]);
    $permission = \Common\Auth\Permissions\Permission::firstOrCreate(['name' => 'admin'], ['display_name' => 'Super Admin']);
    $user->permissions()->attach($permission->id);
});
echo "Initial super admin created. Credentials are in the deployment env file.\n";
