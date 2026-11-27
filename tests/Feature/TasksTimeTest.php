<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksTimeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create(['name' => 'Wiktoria Admin']);
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_quick_add_list_groups_and_update(): void
    {
        $me = $this->admin();
        $other = User::factory()->admin()->create(['name' => 'Kamil Grafik']);
        $client = Client::factory()->create(['name' => 'Zielony Dom']);

        $this->post(route('tasks.store'), ['title' => 'Grafiki na Black Friday', 'client_id' => $client->id, 'due_on' => today()->subDay()->toDateString(), 'estimate' => '2:30'])->assertRedirect();
        $t = Ticket::where('title', 'Grafiki na Black Friday')->first();
        $this->assertSame([$me->id, 150, 'task'], [$t->assigned_to, $t->estimate_minutes, $t->type->value]);

        $this->post(route('tasks.store'), ['title' => 'Raport miesięczny', 'client_id' => $client->id, 'assigned_to' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, Ticket::where('title', 'Raport miesięczny')->value('assigned_to'));
        // Tytuły zadań pokazuje też boczna lista zleceń — sprawdzamy więc dane widoku.
        $titles = fn ($groups) => collect($groups)->flatten()->pluck('title')->all();
        $this->get(route('tasks.index'))->assertOk()->assertSeeInOrder([__('task.g_overdue'), 'Grafiki na Black Friday'])
            ->assertViewHas('groups', fn ($g) => $titles($g) === ['Grafiki na Black Friday'] && $g['overdue']->count() === 1);
        $this->get(route('tasks.index', ['who' => 'all']))->assertViewHas('groups', fn ($g) => count($titles($g)) === 2);
        $this->get(route('tasks.index', ['who' => $other->id, 'view' => 'board']))->assertOk()
            ->assertViewHas('columns', fn ($c) => $titles($c) === ['Raport miesięczny']);

        $this->patch(route('tasks.update', $t), ['assigned_to' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $t->fresh()->assigned_to);
        $this->assertSame(today()->subDay()->toDateString(), $t->fresh()->due_on->toDateString()); // termin bez zmian
        $this->patch(route('tasks.update', $t), ['done' => 1]);
        $this->assertSame(TicketStatus::Done, $t->fresh()->status);
    }

    public function test_timer_start_switch_stop_and_cap(): void
    {
        $me = $this->admin();
        $client = Client::factory()->create();
        [$a, $b] = [Ticket::factory()->create(['client_id' => $client->id]), Ticket::factory()->create(['client_id' => $client->id])];

        $this->travelTo(now()->setTime(9, 0));
        $this->post(route('timer.start', $a))->assertSessionHas('status', __('task.timer_started'));
        $this->get(route('tasks.index'))->assertSee('data-timer-since', false);
        $this->travel(25)->minutes();
        $this->post(route('timer.start', $b)); // przełączenie zapisuje czas przy A
        $this->assertSame(25, (int) TimeEntry::where('ticket_id', $a->id)->sum('minutes'));
        $this->assertSame($b->id, $me->fresh()->timer_ticket_id);

        $this->travel(61)->minutes();
        $this->post(route('timer.stop'), ['note' => 'Poprawki', 'internal' => 1])->assertSessionHas('status');
        $e = TimeEntry::where('ticket_id', $b->id)->first();
        $this->assertSame([61, 'Poprawki', false, 'timer'], [$e->minutes, $e->note, $e->billable, $e->source]);
        $this->assertNull($me->fresh()->timer_ticket_id);

        // Zapomniany stoper: zapis ucięty do 10 h i ostrzeżenie.
        $this->post(route('timer.start', $a));
        $this->travel(30)->hours();
        $this->post(route('timer.stop'))->assertSessionHas('error');
        $this->assertSame(TimeTracker::MAX_MINUTES, TimeEntry::where('ticket_id', $a->id)->latest('id')->first()->minutes);

        $this->post(route('timer.start', $a));
        $this->post(route('timer.discard'));
        $this->assertNull($me->fresh()->timer_ticket_id);
        $this->assertSame(2, TimeEntry::where('ticket_id', $a->id)->count());
    }

    public function test_time_week_team_clients_and_export(): void
    {
        $me = $this->admin();
        $client = Client::factory()->create(['name' => 'FitLife', 'hourly_rate' => 200]);
        $t = Ticket::factory()->create(['client_id' => $client->id, 'title' => 'Kampania świąteczna']);
        $monday = today()->startOfWeek();

        $this->post(route('time.store'), ['ticket_id' => $t->id, 'hours' => '1:30', 'spent_on' => $monday->toDateString(), 'note' => 'Brief'])->assertRedirect();
        $this->post(route('time.store'), ['ticket_id' => $t->id, 'hours' => '0,5', 'spent_on' => $monday->toDateString(), 'internal' => 1]);
        $this->post(route('time.store'), ['ticket_id' => $t->id, 'hours' => '1', 'spent_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('spent_on');

        $this->get(route('time.index'))->assertOk()->assertSee('Kampania świąteczna')->assertSee('2:00');
        $this->get(route('time.index', ['tab' => 'team']))->assertOk()->assertSee('Wiktoria Admin')->assertSee('75%');
        $this->get(route('time.index', ['tab' => 'clients', 'month' => $monday->format('Y-m')]))->assertOk()->assertSee('FitLife')->assertSee('300 zł');

        $csv = $this->get(route('time.export', ['month' => $monday->format('Y-m')]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringContainsString('Kampania świąteczna', $csv);
        $this->assertStringContainsString(';1,5;', $csv);
        $this->assertStringContainsString(';1,5;tak;200;300', $csv);

        $e = TimeEntry::first();
        $this->patch(route('time.update', $e), ['hours' => '2', 'note' => 'Brief i plan'])->assertRedirect();
        $this->assertSame(120, $e->fresh()->minutes);
        $this->delete(route('time.destroy', $e))->assertRedirect();
        $this->assertSame(1, TimeEntry::count());
    }

    public function test_client_cannot_use_tasks_or_time(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client);
        $t = Ticket::factory()->create(['client_id' => $client->client_id]);
        $this->get(route('tasks.index'))->assertForbidden();
        $this->get(route('time.index'))->assertForbidden();
        $this->post(route('timer.start', $t))->assertForbidden();
        $this->get(route('time.export'))->assertForbidden();
    }
}
