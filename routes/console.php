<?php

use App\Models\SystemLog;
use App\Models\UploadSession;
use App\Services\RecurringTickets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| Jeden wpis crona na hostingu (co minutę):
|   * * * * * cd /ścieżka/do/panelu && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::call(fn () => Cache::forever('agency:last_cron', now()->toIso8601String()))
    ->everyMinute()->name('heartbeat');

// Kolejka w bazie danych obsługiwana przez cron (bez stałych procesów).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()->withoutOverlapping()->name('queue');

Schedule::command('agency:stall-check')->hourly()->withoutOverlapping();
Schedule::command('agency:digests')->dailyAt('07:30');
Schedule::command('agency:backup')->dailyAt('02:15');

Schedule::call(function () {
    $n = app(RecurringTickets::class)->runDue();
    if ($n) {
        SystemLog::record('recurring', "Created {$n} recurring tickets");
    }
})->dailyAt('06:00')->name('recurring-tickets');

// Porzucone wysyłki w kawałkach.
Schedule::call(function () {
    UploadSession::where('updated_at', '<', now()->subDay())->get()->each(function ($s) {
        Storage::disk('local')->deleteDirectory($s->chunkDir());
        $s->delete();
    });
})->daily()->name('cleanup-uploads');
