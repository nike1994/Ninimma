<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, config('agency.locales'), true), 404);
    session(['locale' => $locale]);
    if ($user = auth()->user()) {
        $user->update(['locale' => $locale]);
    }

    return back();
})->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');
});

// Kopie zapasowe pobierane przez homelab (token w nagłówku).
Route::get('/backup/latest', [BackupController::class, 'latest'])->middleware('throttle:10,1')->name('backup.latest');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'logout'])->name('logout');
    Route::get('/two-factor', [Auth\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [Auth\TwoFactorController::class, 'verify'])->middleware('throttle:6,1');
    Route::get('/two-factor/setup', [Auth\TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [Auth\TwoFactorController::class, 'confirm'])->middleware('throttle:6,1');
});

Route::middleware(['auth', '2fa', 'terms'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/poll', [TicketController::class, 'poll'])->name('tickets.poll');
    Route::post('/tickets/{ticket}/messages', [MessageController::class, 'store'])->name('tickets.messages.store');

    // Wyceny i odbiór.
    Route::post('/quotes/{quote}/decide', [\App\Http\Controllers\QuoteController::class, 'decide'])->name('quotes.decide');
    Route::post('/tickets/{ticket}/handover/accept', [\App\Http\Controllers\QuoteController::class, 'acceptHandover'])->name('tickets.handover.accept');
    Route::get('/billing', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::get('/terms', [\App\Http\Controllers\TermsController::class, 'show'])->name('terms.show');
    Route::post('/terms', [\App\Http\Controllers\TermsController::class, 'accept'])->name('terms.accept');

    // Tablice (Excalidraw).
    Route::post('/tickets/{ticket}/boards', [\App\Http\Controllers\BoardController::class, 'store'])->name('boards.store');
    Route::get('/boards/{board}', [\App\Http\Controllers\BoardController::class, 'show'])->name('boards.show');
    Route::get('/boards/{board}/sync', [\App\Http\Controllers\BoardController::class, 'poll'])->name('boards.poll');
    Route::post('/boards/{board}/sync', [\App\Http\Controllers\BoardController::class, 'push'])->middleware('throttle:240,1')->name('boards.push');
    Route::post('/boards/{board}/versions', [\App\Http\Controllers\BoardController::class, 'saveVersion'])->name('boards.versions.store');
    Route::post('/boards/{board}/session-end', [\App\Http\Controllers\BoardController::class, 'sessionEnd'])->name('boards.session-end');
    Route::get('/boards/{board}/versions/{version}', [\App\Http\Controllers\BoardController::class, 'version'])->name('boards.versions.show');
    Route::post('/boards/{board}/versions/{version}/restore', [\App\Http\Controllers\BoardController::class, 'restore'])->name('boards.versions.restore');
    Route::post('/boards/{board}/seen', [\App\Http\Controllers\BoardController::class, 'seen'])->name('boards.seen');
    Route::post('/boards/{board}/files', [\App\Http\Controllers\BoardController::class, 'storeFile'])->name('boards.files.store');
    Route::get('/boards/{board}/files/{fileId}', [\App\Http\Controllers\BoardController::class, 'file'])->name('boards.files.show');

    Route::post('/uploads', [UploadController::class, 'start'])->name('uploads.start');
    Route::post('/uploads/{upload}/chunk', [UploadController::class, 'chunk'])->name('uploads.chunk');
    Route::post('/uploads/{upload}/finish', [UploadController::class, 'finish'])->name('uploads.finish');
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::get('/files/{usage}/{name?}', [FileController::class, 'show'])->name('files.show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');

    // Plan publikacji (klient: udostępnione publikacje).
    Route::get('/publications', [\App\Http\Controllers\Social\ClientPublicationController::class, 'index'])->name('publications.index');
    Route::post('/publications/{publication}/respond', [\App\Http\Controllers\Social\ClientPublicationController::class, 'respond'])->name('publications.respond');
    Route::get('/publications/calendar', \App\Http\Controllers\Social\CalendarController::class)->name('publications.calendar');

    // Social media (administrator).
    Route::middleware('admin')->prefix('social')->name('social.')->group(function () {
        Route::get('/', \App\Http\Controllers\Social\SocialDashboardController::class)->name('dashboard');
        Route::get('/calendar', \App\Http\Controllers\Social\CalendarController::class)->name('calendar');
        Route::resource('contents', \App\Http\Controllers\Social\ContentController::class)->except('show');
        Route::get('/stats', [\App\Http\Controllers\Social\StatsController::class, 'index'])->name('stats');
        Route::post('/stats', [\App\Http\Controllers\Social\StatsController::class, 'store']);
        Route::resource('campaigns', \App\Http\Controllers\Social\CampaignController::class)->except(['show', 'destroy']);
        Route::post('/tasks', [\App\Http\Controllers\Social\TaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/{ticket}/toggle', [\App\Http\Controllers\Social\TaskController::class, 'toggle'])->name('tasks.toggle');
        Route::post('/notes', [\App\Http\Controllers\Social\NoteController::class, 'store'])->name('notes.store');
        Route::patch('/notes/{note}', [\App\Http\Controllers\Social\NoteController::class, 'toggle'])->name('notes.toggle');
        Route::delete('/notes/{note}', [\App\Http\Controllers\Social\NoteController::class, 'destroy'])->name('notes.destroy');
        Route::get('/accounts', [\App\Http\Controllers\Social\AccountController::class, 'index'])->name('accounts');
        Route::post('/accounts', [\App\Http\Controllers\Social\AccountController::class, 'store']);
        Route::patch('/accounts/{account}/toggle', [\App\Http\Controllers\Social\AccountController::class, 'toggle'])->name('accounts.toggle');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::patch('/tickets/{ticket}/details', [TicketController::class, 'details'])->name('tickets.details');
        Route::post('/tickets/{ticket}/time', [TicketController::class, 'logTime'])->name('tickets.time');
        Route::post('/tickets/{ticket}/quotes', [\App\Http\Controllers\QuoteController::class, 'store'])->name('tickets.quotes.store');
        Route::post('/tickets/{ticket}/handover', [\App\Http\Controllers\QuoteController::class, 'requestHandover'])->name('tickets.handover');
        Route::post('/cost-documents', [\App\Http\Controllers\BillingController::class, 'store'])->name('cost-documents.store');
        Route::patch('/cost-documents/{document}/paid', [\App\Http\Controllers\BillingController::class, 'togglePaid'])->name('cost-documents.paid');
        Route::get('/terms', [\App\Http\Controllers\TermsController::class, 'edit'])->name('terms.edit');
        Route::put('/terms', [\App\Http\Controllers\TermsController::class, 'update']);
        Route::post('/tickets/{ticket}/remind', [TicketController::class, 'remind'])->name('tickets.remind');

        Route::resource('clients', Admin\ClientController::class)->except('destroy');
        Route::post('/clients/{client}/invite', [Admin\ClientController::class, 'invite'])->name('clients.invite');
        Route::patch('/users/{user}/toggle', [Admin\ClientController::class, 'toggleUser'])->name('users.toggle');
        Route::get('/team', [Admin\TeamController::class, 'index'])->name('team');
        Route::post('/team', [Admin\TeamController::class, 'invite']);

        Route::resource('templates', Admin\TemplateController::class)->except(['show', 'create']);
        Route::post('/templates/{template}/run', [Admin\TemplateController::class, 'run'])->name('templates.run');

        Route::get('/system', [Admin\SystemController::class, 'index'])->name('system');
    });
});
