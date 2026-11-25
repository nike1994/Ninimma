<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\BoardVersion;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\ExportPackage;
use App\Models\FileUsage;
use App\Models\StoredFile;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ArchiveService;
use App\Services\BoardService;
use App\Services\FileStore;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    /** Zlecenie z wiadomością z załącznikiem i tablicą z trzema wersjami (jedna opisana). */
    private function ticketWithData(User $admin): Ticket
    {
        $ticket = Ticket::factory()->create();
        $tmp = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($tmp, str_repeat('X', 5000));
        $file = app(FileStore::class)->storeLocalFile($tmp, 'text/plain');
        $usage = FileUsage::create(['file_id' => $file->id, 'name' => 'log.txt', 'uploaded_by' => $admin->id]);
        app(TicketService::class)->postMessage($ticket, $admin, 'Załącznik', false, [$usage->id]);

        $boards = app(BoardService::class);
        $board = $boards->create($ticket, $admin, 'Plan');
        foreach ([1, 2, 3] as $v) {
            $boards->merge($board, $admin, [['id' => 'a', 'type' => 'rectangle', 'version' => $v, 'x' => $v]]);
            $boards->saveVersion($board, $admin, $v === 2 ? 'Opisana' : null, $v !== 2);
        }
        app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Done);
        $ticket->forceFill(['closed_at' => now()->subMonths(13)])->save();

        return $ticket;
    }

    public function test_archive_keeps_final_and_described_versions_and_restores(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = $this->admin();
        $ticket = $this->ticketWithData($admin);

        $this->assertCount(1, app(ArchiveService::class)->readyToArchive());
        $this->assertSame(1, app(ArchiveService::class)->archiveDue());
        $ticket->refresh();
        $this->assertSame('archived', $ticket->archive_state);
        $this->assertSame(['Opisana', null], BoardVersion::orderBy('id')->pluck('description')->all()); // opisana + końcowa
        $this->assertSame(1, StoredFile::count()); // załącznik zostaje

        $this->post(route('admin.tickets.restore', $ticket))->assertRedirect();
        $this->assertSame('active', $ticket->fresh()->archive_state);
    }

    public function test_offline_move_and_restore_from_package(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = $this->admin();
        $ticket = $this->ticketWithData($admin);
        app(ArchiveService::class)->archive($ticket);
        $file = StoredFile::first();

        $this->post(route('admin.archive.offline'), ['tickets' => [$ticket->id]])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('offline', $ticket->archive_state);
        $pkg = ExportPackage::where('kind', 'offline')->first();
        $this->assertSame($pkg->name, $ticket->offline_package);
        $this->assertSame('offline', $file->fresh()->location);
        Storage::disk('local')->assertMissing($file->path);
        Storage::disk('local')->assertExists($pkg->path());

        // Plik jest w paczce; po wgraniu paczki wraca na hosting.
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($pkg->path()));
        $data = json_decode($zip->getFromName('data.json'), true);
        $this->assertSame('Załącznik', $data['tickets'][0]['messages'][0]['body']);
        $zip->close();
        $this->post(route('admin.tickets.restore', $ticket))->assertSessionHasErrors();

        $upload = new UploadedFile(Storage::disk('local')->path($pkg->path()), $pkg->name, 'application/zip', null, true);
        $this->post(route('admin.archive.restore-package'), ['package' => $upload])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('hosting', $file->fresh()->location);
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame('archived', $ticket->fresh()->archive_state);
    }

    public function test_client_export_retention_delete_and_credentials(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = $this->admin();
        $ticket = $this->ticketWithData($admin);
        $client = $ticket->client;

        $this->get(route('admin.clients.export', $client))->assertOk();
        $this->assertSame(1, ExportPackage::where('kind', 'client')->count());

        // Okres przechowywania: zarchiwizowane zlecenie starsze niż 12 mies. jest usuwane z plikami.
        app(ArchiveService::class)->archive($ticket);
        $client->update(['retention_months' => 12]);
        $this->assertSame(1, app(ArchiveService::class)->purgeExpired());
        $this->assertNull(Ticket::find($ticket->id));
        $this->assertSame(0, StoredFile::count());
        $this->assertTrue(SystemLog::where('type', 'ticket_deleted')->exists());

        // Usunięcie na wniosek: wymaga wpisania numeru.
        $t2 = Ticket::factory()->create(['client_id' => $client->id]);
        $this->delete(route('admin.tickets.destroy', $t2), ['confirm' => 'zle'])->assertSessionHasErrors();
        $this->delete(route('admin.tickets.destroy', $t2), ['confirm' => $t2->id])->assertRedirect();
        $this->assertNull(Ticket::find($t2->id));

        // Kartoteka: dostęp szyfrowany w bazie, odsłonięcie logowane, klient nie ma dostępu.
        $this->post(route('admin.credentials.store', $client), ['label' => 'Panel WP', 'username' => 'admin', 'secret' => 'S3kret!']);
        $cred = ClientCredential::first();
        $this->assertStringNotContainsString('S3kret!', \DB::table('client_credentials')->value('secret'));
        $this->getJson(route('admin.credentials.reveal', $cred))->assertJson(['secret' => 'S3kret!']);
        $this->assertTrue(SystemLog::where('type', 'credential_reveal')->exists());
        $user = User::factory()->create(['client_id' => $client->id]);
        $this->actingAs($user)->getJson(route('admin.credentials.reveal', $cred))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.archive'))->assertOk();
        $this->get(route('admin.clients.show', $client))->assertOk()->assertSee('Panel WP')->assertDontSee('S3kret!');
    }

    public function test_backup_endpoints_for_homelab(): void
    {
        Storage::fake('local');
        Notification::fake();
        $admin = $this->admin();
        config(['agency.backup_token' => 'tok']);
        $pkg = app(ArchiveService::class)->export('ticket', collect([Ticket::factory()->create()]), null, $admin);
        $this->getJson('/backup/exports')->assertNotFound();
        $this->getJson('/backup/exports', ['Authorization' => 'Bearer tok'])->assertOk()->assertJsonFragment(['name' => $pkg->name]);
        $this->get('/backup/exports/'.$pkg->name, ['Authorization' => 'Bearer tok'])->assertOk();
    }

    public function test_board_version_preview_rejects_scripts(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $boards = app(BoardService::class);
        $board = $boards->create(Ticket::factory()->create(), $admin, 'B');
        $boards->merge($board, $admin, [['id' => 'a', 'type' => 'rectangle', 'version' => 1]]);
        $v = $boards->saveVersion($board, $admin, 'v1');
        $this->postJson(route('boards.versions.preview', [$board, $v]), ['preview' => '<svg onload="alert(1)"></svg>']);
        $this->assertNull($v->fresh()->preview_usage_id);
        $this->postJson(route('boards.versions.preview', [$board, $v]), ['preview' => '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>']);
        $this->assertNotNull($v->fresh()->preview_usage_id);
        $this->get(route('files.show', $v->fresh()->preview_usage_id))->assertOk()->assertHeader('Content-Security-Policy');
    }
}
