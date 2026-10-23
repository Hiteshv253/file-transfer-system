<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\DeleteExpiredLinks;
use App\Console\Commands\ResetDemoSite;

\Illuminate\Support\Facades\Artisan::command('local-storage:verify {--hashes : Verify SHA-256 in addition to file size}', function () {
    $disk = \Illuminate\Support\Facades\Storage::build(['driver' => 'local', 'root' => config('local-storage.root'), 'throw' => true]);
    $checked = $missing = $damaged = $withoutHash = 0;
    \App\Models\StoredDocument::chunkById(500, function ($documents) use ($disk, &$checked, &$missing, &$damaged, &$withoutHash) {
        foreach ($documents as $document) {
            $checked++;
            if (!$disk->exists($document->path)) {
                $missing++;
                $this->error('MISSING file #'.$document->id.' (owner #'.$document->owner_id.'): '.$document->name);
                continue;
            }
            if ($disk->size($document->path) !== (int) $document->size) {
                $damaged++;
                $this->error('SIZE MISMATCH file #'.$document->id.': '.$document->name);
                continue;
            }
            if ($this->option('hashes')) {
                if (!$document->sha256) $withoutHash++;
                elseif (hash_file('sha256', $disk->path($document->path)) !== $document->sha256) {
                    $damaged++;
                    $this->error('CHECKSUM MISMATCH file #'.$document->id.': '.$document->name);
                }
            }
        }
    });
    $this->line("Checked: $checked; missing: $missing; damaged: $damaged; older files without checksum: $withoutHash");
    return ($missing || $damaged) ? 1 : 0;
})->purpose('Read-only audit of active file records against private server storage');

\Illuminate\Support\Facades\Artisan::command('local-storage:admin {email} {--name=Super Admin}', function () {
    $email = $this->argument('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email address.');
        return 1;
    }
    if (\App\Models\User::where('email', $email)->exists()) {
        $this->error('Account already exists; no changes made.');
        return 1;
    }
    $password = \Illuminate\Support\Str::password(18);
    \Illuminate\Support\Facades\DB::transaction(function () use ($email, $password) {
        $user = \App\Models\User::create(['name' => $this->option('name'), 'email' => $email,
            'password' => $password, 'email_verified_at' => now()]);
        $permission = \Common\Auth\Permissions\Permission::firstOrCreate(['name' => 'admin'], ['display_name' => 'Super Admin']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
    });
    $this->info('Super admin created: '.$email);
    $this->line('Temporary password: '.$password);
})->purpose('Create a local file portal super admin with a generated password');

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command(DeleteExpiredLinks::class)->everyMinute();

if (config('app.demo')) {
    Schedule::command(ResetDemoSite::class)->daily();
}
