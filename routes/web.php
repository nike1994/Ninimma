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

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/poll', [TicketController::class, 'poll'])->name('tickets.poll');
    Route::post('/tickets/{ticket}/messages', [MessageController::class, 'store'])->name('tickets.messages.store');

    Route::post('/uploads', [UploadController::class, 'start'])->name('uploads.start');
    Route::post('/uploads/{upload}/chunk', [UploadController::class, 'chunk'])->name('uploads.chunk');
    Route::post('/uploads/{upload}/finish', [UploadController::class, 'finish'])->name('uploads.finish');
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::get('/files/{usage}/{name?}', [FileController::class, 'show'])->name('files.show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::patch('/tickets/{ticket}/details', [TicketController::class, 'details'])->name('tickets.details');
        Route::post('/tickets/{ticket}/time', [TicketController::class, 'logTime'])->name('tickets.time');
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
