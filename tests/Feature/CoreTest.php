<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\TicketMessagePosted;
use App\Notifications\TicketNeedsClient;
use App\Services\TicketService;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function actingAsAdmin(?User $admin = null): User
    {
        $admin ??= $this->admin();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_client_cannot_see_other_clients_tickets(): void
    {
        $mine = Ticket::factory()->create();
        $other = Ticket::factory()->create();
        $user = User::factory()->create(['client_id' => $mine->client_id]);

        $this->actingAs($user)->get(route('tickets.show', $mine))->assertOk();
        $this->actingAs($user)->get(route('tickets.show', $other))->assertForbidden();
        $this->actingAs($user)->get(route('tickets.index'))->assertSee($mine->title)->assertDontSee($other->title);
    }

    public function test_internal_notes_are_hidden_from_client(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $service = app(TicketService::class);
        $service->postMessage($ticket, $admin, 'Widoczna odpowiedź', false);
        $service->postMessage($ticket, $admin, 'Tajna notatka', true);

        $this->actingAs($client)->get(route('tickets.show', $ticket))
            ->assertSee('Widoczna odpowiedź')->assertDontSee('Tajna notatka');
        $this->actingAs($client)->getJson(route('tickets.poll', [$ticket, 'after' => 0]))
            ->assertOk()->assertDontSee('Tajna notatka');
        $this->actingAsAdmin($admin);
        $this->get(route('tickets.show', $ticket))->assertSee('Tajna notatka');

        Notification::assertSentTo($client, TicketMessagePosted::class, 1);
    }

    public function test_client_cannot_post_internal_note_or_change_status(): void
    {
        Notification::fake();
        $ticket = Ticket::factory()->create(['status' => 'waiting_client']);
        $client = User::factory()->create(['client_id' => $ticket->client_id]);

        $this->actingAs($client)->post(route('tickets.messages.store', $ticket), ['body' => 'Hej', 'internal' => 1])->assertRedirect();
        $this->assertFalse($ticket->messages()->first()->is_internal);
        // Odpowiedź klienta oddaje piłkę agencji.
        $this->assertSame(TicketStatus::WithAgency, $ticket->fresh()->status);

        $this->actingAs($client)->patch(route('admin.tickets.update', $ticket), ['status' => 'done'])->assertForbidden();
    }

    public function test_admin_requires_two_factor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'client_id' => null]);
        $this->actingAs($admin)->get('/')->assertRedirect(route('two-factor.setup'));

        $admin = $this->admin();
        $this->actingAs($admin)->get('/')->assertRedirect(route('two-factor.challenge'));
        $this->actingAs($admin)->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/two-factor', ['code' => Totp::code($admin->two_factor_secret)])->assertRedirect();
        $this->get('/')->assertOk();
    }

    public function test_totp_matches_rfc_vector(): void
    {
        // RFC 6238, sekret "12345678901234567890", T=59 → 94287082 (8 cyfr) → 287082 (6 cyfr)
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('287082', Totp::code($secret, 59));
    }

    public function test_invitation_flow(): void
    {
        Notification::fake();
        $admin = $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->post(route('admin.clients.invite', $client), ['name' => 'Anna', 'email' => 'anna@example.com', 'locale' => 'en'])->assertRedirect();
        $token = null;
        Notification::assertSentOnDemand(InvitationNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        auth()->logout();
        $this->get(route('invitation.show', $token))->assertOk();
        $this->post(route('invitation.accept', $token), [
            'name' => 'Anna K', 'password' => 'bardzo-tajne-1', 'password_confirmation' => 'bardzo-tajne-1', 'terms' => 1,
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'anna@example.com')->first();
        $this->assertSame($client->id, $user->client_id);
        $this->assertSame('en', $user->locale);
        $this->assertNull(Invitation::findValid($token));
    }

    public function test_chunked_upload_dedup_and_access(): void
    {
        Storage::fake('local');
        Notification::fake();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $stranger = User::factory()->create();
        $content = str_repeat('A', 1500 * 1024);

        $usageIds = [];
        foreach ([1, 2] as $_) {
            $start = $this->actingAs($client)->postJson(route('uploads.start'), ['name' => 'log.txt', 'size' => strlen($content)])->assertOk()->json();
            $this->assertSame(2, $start['total_chunks']);
            foreach (str_split($content, $start['chunk_size']) as $i => $part) {
                $this->postJson(route('uploads.chunk', $start['id']), [
                    'index' => $i, 'chunk' => UploadedFile::fake()->createWithContent('chunk', $part),
                ])->assertOk();
            }
            $usageIds[] = $this->postJson(route('uploads.finish', $start['id']))->assertOk()->json('usage_id');
        }
        $this->assertDatabaseCount('files', 1); // ten sam plik zapisany raz

        $this->post(route('tickets.messages.store', $ticket), ['body' => 'plik', 'uploads' => $usageIds])->assertRedirect();
        $this->get(route('files.show', $usageIds[0]))->assertOk();
        $this->actingAs($stranger)->get(route('files.show', $usageIds[0]))->assertForbidden();
    }

    public function test_client_upload_limit(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client)->postJson(route('uploads.start'), ['name' => 'big.mp4', 'size' => 101 * 1024 * 1024])->assertStatus(422);
    }

    public function test_stall_reminders(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $service = app(TicketService::class);

        $service->changeStatus($ticket, $admin, TicketStatus::WaitingClient);
        Notification::assertSentTo($client, TicketNeedsClient::class, fn ($n) => $n->reminder === 0);

        $this->travel(25)->hours();
        $this->artisan('agency:stall-check');
        $this->assertSame(1, $ticket->fresh()->reminders_sent);
        $this->artisan('agency:stall-check');
        $this->assertSame(1, $ticket->fresh()->reminders_sent);

        $this->travel(7)->days();
        $this->artisan('agency:stall-check');
        $fresh = $ticket->fresh();
        $this->assertSame(2, $fresh->reminders_sent);
        $this->assertTrue($fresh->isStalled());
        $this->artisan('agency:stall-check');
        $this->assertSame(2, $ticket->fresh()->reminders_sent);

        // Odpowiedź klienta zdejmuje zastój.
        $service->postMessage($fresh, $client, 'Już odpisuję', false);
        $this->assertFalse($ticket->fresh()->isStalled());
    }

    public function test_search_includes_message_content(): void
    {
        Notification::fake();
        $admin = $this->actingAsAdmin();
        $ticket = Ticket::factory()->create(['title' => 'Coś innego']);
        app(TicketService::class)->postMessage($ticket, $admin, 'Formularz kontaktowy nie wysyła', false);

        $this->get(route('tickets.index', ['q' => 'kontaktowy']))->assertViewHas('tickets', fn ($p) => $p->total() === 1);
        $this->get(route('tickets.index', ['q' => 'zupełnie-nic']))->assertViewHas('tickets', fn ($p) => $p->total() === 0);
    }

    public function test_recurring_templates_create_tickets(): void
    {
        Notification::fake();
        $this->admin();
        $client = Client::factory()->create();
        \App\Models\TicketTemplate::create([
            'client_id' => $client->id, 'name' => 'Opieka', 'title' => 'Opieka — {month}', 'type' => 'site_review',
            'recurrence' => 'monthly', 'next_run_on' => today(), 'body' => 'Lista kontrolna',
        ]);
        $this->assertSame(1, app(\App\Services\RecurringTickets::class)->runDue());
        $this->assertSame(0, app(\App\Services\RecurringTickets::class)->runDue());
        $this->assertSame(1, $client->tickets()->count());
    }

    public function test_pages_render(): void
    {
        Notification::fake();
        $admin = $this->actingAsAdmin();
        $ticket = Ticket::factory()->create();
        foreach (['dashboard', 'tickets.index', 'files.index', 'tickets.create', 'settings', 'admin.clients.index', 'admin.clients.create',
            'admin.team', 'admin.templates.index', 'admin.system'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.clients.show', $ticket->client))->assertOk();
        $this->get(route('tickets.show', $ticket))->assertOk();

        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $this->actingAs($client)->get(route('dashboard'))->assertOk()->assertSee($ticket->title);
        $this->actingAs($client)->get(route('admin.clients.index'))->assertForbidden();
        $this->actingAs($client)->get(route('files.index'))->assertOk();
        $this->actingAs($client)->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_backup_endpoint_requires_token(): void
    {
        Storage::fake('local');
        config(['agency.backup_token' => 'sekret']);
        $this->artisan('agency:backup')->assertSuccessful();
        $this->get('/backup/latest')->assertNotFound();
        $this->get('/backup/latest', ['Authorization' => 'Bearer sekret'])->assertOk();
    }
}
