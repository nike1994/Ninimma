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

// Przeglądy stron tworzą się same jako zlecenia z listą kontrolną.
Schedule::call(function () {
    $n = app(\App\Services\SiteService::class)->runDueReviews();
    if ($n) {
        SystemLog::record('site_reviews', "Created {$n} site reviews");
    }
})->dailyAt('06:10')->name('site-reviews');

// Reklamy: start i koniec według dat.
Schedule::call(function () {
    [$a, $f] = app(\App\Services\AdService::class)->updateStatuses();
    if ($a || $f) {
        SystemLog::record('ads', "Ads started {$a}, finished {$f}");
    }
})->dailyAt('00:20')->name('ads-status');

// Licencje materiałów: e-mail 30/14/7/1/0 dni przed końcem (każdy próg raz).
Schedule::call(function () {
    $n = app(\App\Services\LicenseGuard::class)->sendAlerts();
    if ($n) {
        SystemLog::record('licenses', "License alerts for {$n} materials");
    }
})->dailyAt('07:40')->name('license-alerts');

// Archiwizacja rok po zamknięciu i usuwanie po okresie przechowywania.
Schedule::call(function () {
    $a = app(\App\Services\ArchiveService::class);
    $n = $a->archiveDue();
    $d = $a->purgeExpired();
    if ($n || $d) {
        SystemLog::record('archive', "Archived {$n}, deleted {$d} tickets");
    }
})->dailyAt('03:10')->name('archive');

// Porzucone wysyłki w kawałkach.
Schedule::call(function () {
    UploadSession::where('updated_at', '<', now()->subDay())->get()->each(function ($s) {
        Storage::disk('local')->deleteDirectory($s->chunkDir());
        $s->delete();
    });
    // Podglądy ze znakiem wodnym (z datą) — starsze niż 2 dni do usunięcia, odtworzą się przy potrzebie.
    $disk = Storage::disk('local');
    foreach ($disk->files('previews') as $f) {
        if ($disk->lastModified($f) < now()->subDays(2)->timestamp) {
            $disk->delete($f);
        }
    }
})->daily()->name('cleanup-uploads');
