<?php

use Illuminate\Support\Facades\Schedule;

// Needs a cron entry on the server:  * * * * * cd /path/to/site && php artisan schedule:run
Schedule::command('smko:notify-new-files')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('smko:health')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('telegram_claims')->where('expires_at', '<', now()->subDay())->delete())->daily();
