<?php

namespace Tests\Feature;

use App\Http\Controllers\TicketController;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardStats;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_admin_sets_priority_due_date_and_assignee(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();

        $this->patch(route('admin.tickets.details', $ticket), [
            'priority' => 'urgent', 'due_on' => today()->toDateString(), 'assigned_to' => $admin->id,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('urgent', $ticket->priority->value);
        $this->assertTrue($ticket->due_on->isToday());
        $this->assertSame($admin->id, $ticket->assigned_to);
        $this->assertSame(3, $ticket->events()->count());
        // Przypisanie jest notatką wewnętrzną na osi czasu.
        $this->assertTrue($ticket->events()->where('type', 'assigned')->first()->is_internal);
    }

    public function test_time_logging_and_board_column(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $this->assertSame('todo', $ticket->fresh()->boardColumn());
        // Zlecenie utworzone z opisem przez administratora nadal czeka na rozpoczęcie.
        $fresh = app(TicketService::class)->create($admin, ['client_id' => $ticket->client_id, 'title' => 'X', 'type' => 'task'], 'Opis');
        $this->assertSame('todo', $fresh->fresh()->boardColumn());

        $this->post(route('admin.tickets.time', $ticket), ['hours' => '1:30', 'spent_on' => today()->toDateString()])->assertRedirect();
        $this->post(route('admin.tickets.time', $ticket), ['hours' => '0,5', 'spent_on' => today()->toDateString()])->assertRedirect();

        $this->assertSame(120, (int) $ticket->timeEntries()->sum('minutes'));
        $this->assertSame('doing', $ticket->fresh()->boardColumn());
        $this->assertSame(90, TicketController::parseHours('1:30'));
        $this->assertSame(150, TicketController::parseHours('2,5'));
    }

    public function test_client_cannot_log_time_or_see_it(): void
    {
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $this->actingAs($client)->post(route('admin.tickets.time', $ticket), ['hours' => '1', 'spent_on' => today()->toDateString()])->assertForbidden();
        $this->actingAs($client)->patch(route('admin.tickets.details', $ticket), ['priority' => 'urgent'])->assertForbidden();
    }

    public function test_dashboard_counts_and_trends(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $ticket = Ticket::factory()->create(['due_on' => today()]);
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $service = app(TicketService::class);
        $service->postMessage($ticket, $client, 'Wiadomość 1', false);
        $service->postMessage($ticket, $client, 'Wiadomość 2', false);
        $service->logTime($ticket, $admin, 120, today()->toDateString(), null);

        $stats = new DashboardStats($admin, '7d');
        $cards = $stats->cards(Ticket::open()->get());
        $this->assertSame(2, $cards[0]['value']);       // wiadomości od klientów
        $this->assertSame(1, $cards[1]['value']);       // otwarte
        $this->assertSame(1, $cards[2]['value']);       // na dziś
        $this->assertSame('2 h', $cards[4]['value']);// czas pracy
        $this->assertSame(2, $stats->conversations()->first()->unread); // administrator jeszcze nie czytał
        $this->assertSame(50, DashboardStats::trend(150, 100));
        $this->assertNull(DashboardStats::trend(5, 0));
        $this->assertSame('1,5 h', \App\Models\TimeEntry::hours(90));
        $this->assertSame(8, \App\Support\Chart::niceMax(7));
        $this->assertSame(20, \App\Support\Chart::niceMax(13));
        $this->assertSame(12, \App\Support\Chart::niceMax(9));

        $this->get(route('dashboard', ['period' => '30d']))->assertOk()->assertSee('Wiadomość 2');
    }
}
