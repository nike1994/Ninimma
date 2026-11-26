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

// Wspólne logowanie z wtyczki WordPress (link podpisany sekretem strony).
Route::get('/sso/wordpress', \App\Http\Controllers\WordPressSsoController::class)->middleware('throttle:20,1')->name('sso.wordpress');

// Kopie zapasowe pobierane przez homelab (token w nagłówku).
Route::get('/backup/latest', [BackupController::class, 'latest'])->middleware('throttle:10,1')->name('backup.latest');
Route::get('/backup/exports', [BackupController::class, 'exports'])->middleware('throttle:10,1')->name('backup.exports');
Route::get('/backup/exports/{name}', [BackupController::class, 'export'])->middleware('throttle:30,1')->name('backup.export');

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

    // Materiały i raporty kampanii (klient: tylko swoje, bez stawek i danych osobowych).
    Route::get('/materials', [\App\Http\Controllers\Influencers\MaterialController::class, 'index'])->name('materials.index');
    Route::get('/reports', [\App\Http\Controllers\Influencers\MaterialController::class, 'clientReports'])->name('reports.index');
    Route::get('/reports/campaigns/{campaign}', [\App\Http\Controllers\Influencers\MaterialController::class, 'report'])->name('reports.campaign');

    // Strony i SEO — klient: raport miesięczny.
    Route::get('/my-sites', \App\Http\Controllers\Sites\ClientSiteController::class)->name('client.sites');

    // Strony, administracja i SEO (administrator).
    Route::middleware('admin')->group(function () {
        $s = \App\Http\Controllers\Sites\SiteController::class;
        $seo = \App\Http\Controllers\Sites\SeoController::class;
        Route::get('/calendar', \App\Http\Controllers\Sites\JointCalendarController::class)->name('calendar');
        Route::resource('sites', $s)->except('destroy');
        Route::post('/sites/{site}/deadlines', [$s, 'storeDeadline'])->name('sites.deadlines.store');
        Route::post('/sites/{site}/sso-secret', [$s, 'ssoSecret'])->name('sites.sso-secret');
        Route::post('/deadlines/{deadline}/renew', [$s, 'renewDeadline'])->name('deadlines.renew');
        Route::post('/sites/{site}/logs', [$s, 'storeLog'])->name('sites.logs.store');
        Route::post('/sites/{site}/reviews', [$s, 'startReview'])->name('sites.reviews.store');
        Route::get('/reviews/{review}', [$s, 'review'])->name('sites.reviews.show');
        Route::put('/reviews/{review}', [$s, 'updateReview'])->name('sites.reviews.update');
        Route::post('/reviews/{review}/problem', [$s, 'problem'])->name('sites.reviews.problem');
        Route::post('/sites/{site}/phrases', [$seo, 'storePhrase'])->name('sites.phrases.store');
        Route::post('/sites/{site}/positions', [$seo, 'storePositions'])->name('sites.positions.store');
        Route::post('/sites/{site}/positions/import', [$seo, 'import'])->name('sites.positions.import');
        Route::post('/sites/{site}/seo-contents', [$seo, 'storeContent'])->name('sites.seo-contents.store');
        Route::post('/sites/{site}/seo-tasks', [$seo, 'storeTask'])->name('sites.seo-tasks.store');
        Route::patch('/seo-tasks/{task}', [$seo, 'updateTask'])->name('seo-tasks.update');
        Route::post('/sites/{site}/backlinks', [$seo, 'storeBacklink'])->name('sites.backlinks.store');
        Route::patch('/backlinks/{backlink}', [$seo, 'toggleBacklink'])->name('backlinks.toggle');
        Route::get('/checklists', [\App\Http\Controllers\Sites\ChecklistController::class, 'index'])->name('checklists.index');
        Route::post('/checklists', [\App\Http\Controllers\Sites\ChecklistController::class, 'store'])->name('checklists.store');
        Route::put('/checklists/{checklist}', [\App\Http\Controllers\Sites\ChecklistController::class, 'update'])->name('checklists.update');
    });

    // Influencerzy (administrator).
    Route::middleware('admin')->group(function () {
        Route::resource('influencers', \App\Http\Controllers\Influencers\InfluencerController::class)->except('destroy');
        $c = \App\Http\Controllers\Influencers\CollaborationController::class;
        Route::post('/collaborations', [$c, 'store'])->name('collaborations.store');
        Route::get('/collaborations/{collaboration}', [$c, 'show'])->name('collaborations.show');
        Route::put('/collaborations/{collaboration}', [$c, 'update'])->name('collaborations.update');
        Route::post('/collaborations/{collaboration}/step', [$c, 'step'])->name('collaborations.step');
        Route::post('/collaborations/{collaboration}/cancel', [$c, 'cancel'])->name('collaborations.cancel');
        Route::post('/collaborations/{collaboration}/shipments', [$c, 'storeShipment'])->name('collaborations.shipments.store');
        Route::patch('/shipments/{shipment}', [$c, 'updateShipment'])->name('shipments.update');
        Route::post('/collaborations/{collaboration}/materials', [$c, 'storeMaterial'])->name('collaborations.materials.store');
        Route::put('/materials/{material}', [$c, 'updateMaterial'])->name('materials.update');
        Route::post('/reminders', [$c, 'storeReminder'])->name('reminders.store');
        Route::patch('/reminders/{reminder}', [$c, 'toggleReminder'])->name('reminders.toggle');

        // Strażnik licencji materiałów.
        Route::get('/licenses', [\App\Http\Controllers\LicenseController::class, 'index'])->name('licenses.index');
        Route::put('/licenses/{material}', [\App\Http\Controllers\LicenseController::class, 'update'])->name('licenses.update');
        Route::post('/licenses/{material}/extend', [\App\Http\Controllers\LicenseController::class, 'extend'])->name('licenses.extend');
    });

    // Tablice (Excalidraw).
    Route::post('/tickets/{ticket}/boards', [\App\Http\Controllers\BoardController::class, 'store'])->name('boards.store');
    Route::get('/boards/{board}', [\App\Http\Controllers\BoardController::class, 'show'])->name('boards.show');
    Route::get('/boards/{board}/sync', [\App\Http\Controllers\BoardController::class, 'poll'])->name('boards.poll');
    Route::post('/boards/{board}/sync', [\App\Http\Controllers\BoardController::class, 'push'])->middleware('throttle:240,1')->name('boards.push');
    Route::post('/boards/{board}/versions', [\App\Http\Controllers\BoardController::class, 'saveVersion'])->name('boards.versions.store');
    Route::post('/boards/{board}/session-end', [\App\Http\Controllers\BoardController::class, 'sessionEnd'])->name('boards.session-end');
    Route::get('/boards/{board}/versions/{version}', [\App\Http\Controllers\BoardController::class, 'version'])->name('boards.versions.show');
    Route::post('/boards/{board}/versions/{version}/restore', [\App\Http\Controllers\BoardController::class, 'restore'])->name('boards.versions.restore');
    Route::post('/boards/{board}/versions/{version}/preview', [\App\Http\Controllers\BoardController::class, 'storePreview'])->name('boards.versions.preview');
    Route::post('/boards/{board}/seen', [\App\Http\Controllers\BoardController::class, 'seen'])->name('boards.seen');
    Route::post('/boards/{board}/files', [\App\Http\Controllers\BoardController::class, 'storeFile'])->name('boards.files.store');
    Route::get('/boards/{board}/files/{fileId}', [\App\Http\Controllers\BoardController::class, 'file'])->name('boards.files.show');

    Route::post('/uploads', [UploadController::class, 'start'])->name('uploads.start');
    Route::post('/uploads/{upload}/chunk', [UploadController::class, 'chunk'])->name('uploads.chunk');
    Route::post('/uploads/{upload}/finish', [UploadController::class, 'finish'])->name('uploads.finish');
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::get('/files/{usage}/poster.jpg', [FileController::class, 'poster'])->name('files.poster');
    Route::post('/files/{usage}/poster', [FileController::class, 'storePoster'])->middleware('throttle:60,1')->name('files.poster.store');
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
        $ad = \App\Http\Controllers\Social\AdController::class;
        Route::get('/ads', [$ad, 'index'])->name('ads.index');
        Route::get('/ads/create', [$ad, 'create'])->name('ads.create');
        Route::post('/ads', [$ad, 'store'])->name('ads.store');
        Route::get('/ads/{ad}', [$ad, 'edit'])->name('ads.edit');
        Route::put('/ads/{ad}', [$ad, 'update'])->name('ads.update');
        Route::delete('/ads/{ad}', [$ad, 'destroy'])->name('ads.destroy');
        Route::post('/ads/{ad}/results', [$ad, 'storeResult'])->name('ads.results.store');
        Route::post('/ads/{ad}/import', [$ad, 'import'])->name('ads.import.one');
        Route::post('/ads-import', [$ad, 'import'])->name('ads.import');
        Route::post('/campaigns/{campaign}/utm', [\App\Http\Controllers\Social\UtmController::class, 'store'])->name('utm.store');
        Route::delete('/utm/{link}', [\App\Http\Controllers\Social\UtmController::class, 'destroy'])->name('utm.destroy');
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

        // Archiwum, paczki eksportu, przenoszenie offline, usuwanie.
        Route::get('/archive', [Admin\ArchiveController::class, 'index'])->name('archive');
        Route::put('/archive/settings', [Admin\ArchiveController::class, 'settings'])->name('archive.settings');
        Route::post('/archive/ready', [Admin\ArchiveController::class, 'archiveAllReady'])->name('archive.ready');
        Route::post('/archive/offline', [Admin\ArchiveController::class, 'moveOffline'])->name('archive.offline');
        Route::post('/archive/restore-package', [Admin\ArchiveController::class, 'restorePackage'])->name('archive.restore-package');
        Route::post('/tickets/{ticket}/archive', [Admin\ArchiveController::class, 'archiveTicket'])->name('tickets.archive');
        Route::post('/tickets/{ticket}/restore', [Admin\ArchiveController::class, 'restore'])->name('tickets.restore');
        Route::get('/tickets/{ticket}/export', [Admin\ArchiveController::class, 'exportTicket'])->name('tickets.export');
        Route::delete('/tickets/{ticket}', [Admin\ArchiveController::class, 'destroyTicket'])->name('tickets.destroy');
        Route::get('/clients/{client}/export', [Admin\ArchiveController::class, 'exportClient'])->name('clients.export');
        Route::get('/packages/{package}', [Admin\ArchiveController::class, 'download'])->name('packages.download');
        Route::delete('/packages/{package}', [Admin\ArchiveController::class, 'destroyPackage'])->name('packages.destroy');

        // Kartoteka klienta: szyfrowane dostępy i ustalenia.
        Route::post('/clients/{client}/credentials', [Admin\CredentialController::class, 'store'])->name('credentials.store');
        Route::get('/credentials/{credential}/reveal', [Admin\CredentialController::class, 'reveal'])->middleware('throttle:30,1')->name('credentials.reveal');
        Route::delete('/credentials/{credential}', [Admin\CredentialController::class, 'destroy'])->name('credentials.destroy');
        Route::put('/clients/{client}/arrangements', [Admin\CredentialController::class, 'arrangements'])->name('clients.arrangements');
    });
});
