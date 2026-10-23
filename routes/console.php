<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\DeleteExpiredLinks;
use App\Console\Commands\ResetDemoSite;

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
