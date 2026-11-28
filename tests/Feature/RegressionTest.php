<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Client;
use App\Models\CostDocument;
use App\Models\FileUsage;
use App\Models\Invitation;
use App\Models\StoredFile;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketNeedsClient;
use App\Services\ArchiveService;
use App\Services\FileStore;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Błędy znalezione w przeglądzie kodu — każdy test pilnuje jednej poprawki. */
class RegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_board_svg_is_served_sandboxed(): void
    {
        Storage::fake('local');
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $board = Board::create(['ticket_id' => $ticket->id, 'name' => 'x']);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->actingAs($client)->postJson(route('boards.files.store', $board),
            ['id' => 'svg1', 'dataURL' => 'data:image/svg+xml;base64,'.base64_encode($svg)])->assertOk();

        $this->admin();
        $response = $this->get(route('boards.files.show', [$board, 'svg1']))->assertOk();
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        // Pliki za logowaniem nie mogą trafić do współdzielonej pamięci podręcznej (pośrednik, CDN).
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_accepting_one_invitation_invalidates_the_others(): void
    {
        Notification::fake();
        $client = Client::factory()->create();
        $attributes = ['email' => 'x@k.test', 'name' => 'X', 'role' => 'client', 'client_id' => $client->id];
        [, $first] = Invitation::issue($attributes);
        [, $second] = Invitation::issue($attributes);
        $form = ['name' => 'X', 'password' => 'haslo-haslo-1', 'password_confirmation' => 'haslo-haslo-1', 'terms' => '1'];
        $this->post(route('invitation.accept', $first), $form)->assertRedirect();

        $user = User::where('email', 'x@k.test')->firstOrFail();
        $user->update(['is_active' => false]);
        auth()->logout();
        $this->flushSession();

        $this->assertNull(Invitation::findValid($second));
        $this->post(route('invitation.accept', $second), $form);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_new_quote_version_notifies_client_again(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $quotes = app(QuoteService::class);
        $quotes->create($ticket, $admin, ['hours_limit' => 2]);
        Notification::assertSentTo($client, TicketNeedsClient::class);

        Notification::fake();
        $ticket->forceFill(['reminders_sent' => 2])->save();
        $quotes->create($ticket->fresh(), $admin, ['hours_limit' => 10]);
        Notification::assertSentTo($client, TicketNeedsClient::class);
        $this->assertSame(0, $ticket->fresh()->reminders_sent);
    }

    public function test_orphan_cleanup_keeps_video_posters(): void
    {
        Storage::fake('local');
        $store = app(FileStore::class);
        $tmp = tempnam(sys_get_temp_dir(), 'p');
        file_put_contents($tmp, 'video-bytes');
        $video = $store->storeLocalFile($tmp, 'video/mp4');
        file_put_contents($tmp, 'poster-bytes');
        $poster = $store->storeLocalFile($tmp, 'image/jpeg');
        file_put_contents($tmp, 'orphan-bytes');
        $orphan = $store->storeLocalFile($tmp);
        $video->forceFill(['poster_id' => $poster->id])->save();
        FileUsage::create(['file_id' => $video->id, 'name' => 'v.mp4']);

        app(ArchiveService::class)->deleteOrphanFiles();

        $this->assertTrue(StoredFile::whereKey($poster->id)->exists());
        $this->assertSame($poster->id, $video->fresh()->poster_id);
        $this->assertFalse(StoredFile::whereKey($orphan->id)->exists());
    }

    public function test_reuploading_an_offline_file_brings_it_back(): void
    {
        Storage::fake('local');
        $store = app(FileStore::class);
        $tmp = tempnam(sys_get_temp_dir(), 'p');
        file_put_contents($tmp, 'big-file');
        $file = $store->storeLocalFile($tmp);
        Storage::disk('local')->delete($file->path);
        $file->update(['location' => 'offline', 'offline_package' => 'pkg.zip']);

        $again = $store->storeLocalFile($tmp);

        $this->assertSame($file->id, $again->id);
        $this->assertSame('hosting', $again->location);
        $this->assertTrue(Storage::disk('local')->exists($again->path));
    }

    public function test_cost_document_with_pdf_and_unpaid_total_over_all_pages(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $client = Client::factory()->create();
        $tmp = tempnam(sys_get_temp_dir(), 'p');
        file_put_contents($tmp, '%PDF-1.4 x');
        $file = app(FileStore::class)->storeLocalFile($tmp);
        $usage = FileUsage::create(['file_id' => $file->id, 'name' => 'f.pdf', 'uploaded_by' => $admin->id]);

        $this->post(route('admin.cost-documents.store'), ['client_id' => $client->id, 'number' => 'FV/1', 'amount' => '100',
            'issued_on' => '2026-11-01', 'uploads' => [$usage->id]])->assertRedirect();
        $this->assertSame($usage->id, CostDocument::firstOrFail()->file_usage_id);

        foreach (range(2, 35) as $i) {
            CostDocument::create(['client_id' => $client->id, 'number' => 'FV/'.$i, 'amount' => 100, 'issued_on' => '2026-10-01', 'is_paid' => false]);
        }
        $this->get(route('billing.index'))->assertOk()->assertViewHas('unpaidTotal', fn ($total) => (float) $total === 3500.0);
    }

    public function test_long_log_message_is_truncated_and_bad_month_does_not_crash(): void
    {
        $this->assertLessThanOrEqual(255, mb_strlen(SystemLog::record('test', str_repeat('ż', 600))->message));

        $this->admin();
        $this->get(route('social.calendar', ['month' => 'abc']))->assertOk();
        $this->get(route('social.calendar').'?month[]=1')->assertOk();
    }
}
