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

        $this->post(route('tasks.store'), ['lines' => 'Grafiki na Black Friday #zielony 2:30', 'client_id' => ''])->assertRedirect();
        Ticket::where('title', 'Grafiki na Black Friday')->update(['due_on' => today()->subDay()]);
        $t = Ticket::where('title', 'Grafiki na Black Friday')->first();
        $this->assertSame([$me->id, 150, 'task'], [$t->assigned_to, $t->estimate_minutes, $t->type->value]);

        $this->post(route('tasks.store'), ['lines' => 'Raport miesięczny @kamil', 'client_id' => $client->id])->assertSessionHasNoErrors();
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

    public function test_parser_understands_one_line(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-27 10:00')); // piątek
        $clients = collect([(object) ['id' => 1, 'name' => 'Zielony Dom'], (object) ['id' => 2, 'name' => 'FitLife'], (object) ['id' => 3, 'name' => 'Studio Blu']]);
        $team = collect([(object) ['id' => 10, 'name' => 'Wiktoria Admin'], (object) ['id' => 11, 'name' => 'Ola Mazur'], (object) ['id' => 12, 'name' => 'Ołeksandr Nowak']]);
        $p = new \App\Services\TaskParser($clients, $team, 10);

        $r = $p->parse('Grafiki na BF #zielony @ola jutro 2h !');
        $this->assertSame(['Grafiki na BF', 1, 11, '2026-11-28', 120, 'high', []], [$r['title'], $r['client_id'], $r['assigned_to'], $r['due_on']->toDateString(), $r['estimate'], $r['priority'], $r['errors']]);

        $r = $p->parse('Raport miesięczny #fit pn 30m !!');
        $this->assertSame(['Raport miesięczny', 2, 10, '2026-11-30', 30, 'urgent'], [$r['title'], $r['client_id'], $r['assigned_to'], $r['due_on']->toDateString(), $r['estimate'], $r['priority']]);

        $this->assertSame('2026-12-15', $p->parse('Opisy #blu 15.12')['due_on']->toDateString());
        $this->assertSame('2027-01-05', $p->parse('Plan #blu 5.01')['due_on']->toDateString());
        $this->assertSame('2026-11-30', $p->parse('Spotkanie #blu za 3 dni 1,5h')['due_on']->toDateString());
        $this->assertSame(90, $p->parse('Spotkanie #blu za 3 dni 1,5h')['estimate']);
        $this->assertSame('2026-11-27', $p->parse('Telefon do klienta dziś #studio')['due_on']->toDateString());
        $this->assertSame(3, $p->parse('Telefon do klienta dziś #studio')['client_id']);
        $this->assertSame('Wersja 2.0 strony', $p->parse('Wersja 2.0 strony #blu')['title']);
        // Godzina spotkania i numer wersji zostają w tytule.
        $r = $p->parse('Spotkanie o 15:30 #blu jutro 1h');
        $this->assertSame(['Spotkanie o 15:30', 60, '2026-11-28'], [$r['title'], $r['estimate'], $r['due_on']->toDateString()]);
        $r = $p->parse('Wdrożenie wersja 1.5 #blu');
        $this->assertSame(['Wdrożenie wersja 1.5', null], [$r['title'], $r['due_on']]);
        $this->assertNull($p->parse('Telefon 14:00 #blu')['estimate']);
        $this->assertSame(90, $p->parse('Montaż #blu 1:30')['estimate']);

        $bad = $p->parse('Coś #nieznany @ktos');
        $this->assertCount(3, $bad['errors']); // klient, osoba, brak klienta

        // Lista: wypunktowania i puste linijki, klient domyślny dla linijek bez #.
        $many = $p->parseMany("- Brief\n\n• Grafiki #fit\n3) Raport", 3);
        $this->assertSame([['Brief', 3], ['Grafiki', 2], ['Raport', 3]], array_map(fn ($i) => [$i['title'], $i['client_id']], $many));

        // Zestaw: „+N” od daty startu.
        $set = new \App\Services\TaskParser($clients, $team, 10, \Carbon\Carbon::parse('2026-12-01'));
        $this->assertSame('2026-12-08', $set->parse('Publikacja +7', 1)['due_on']->toDateString());
    }

    public function test_paste_many_lines_preview_and_sets(): void
    {
        $me = $this->admin();
        User::factory()->admin()->create(['name' => 'Ola Mazur']);
        $client = Client::factory()->create(['name' => 'FitLife']);
        $campaign = \App\Models\Campaign::create(['client_id' => $client->id, 'name' => 'Zima', 'status' => 'active']);

        $this->postJson(route('tasks.parse'), ['lines' => "Grafiki @ola jutro 2h\nRaport", 'client_id' => $client->id])->assertOk()
            ->assertJsonCount(2)->assertJsonPath('0.client', 'FitLife')->assertJsonPath('0.person', 'Ola Mazur')->assertJsonPath('0.estimate', '2:00');

        // Linijka z błędem: nic się nie zapisuje, tekst zostaje w polu.
        $this->post(route('tasks.store'), ['lines' => "Dobre\nZłe #nieznany", 'client_id' => $client->id])->assertSessionHasErrors('lines')->assertSessionHasInput('lines');
        $this->assertSame(0, Ticket::count());

        $this->post(route('tasks.store'), ['lines' => "Grafiki @ola jutro 2h\nRaport !\n\n- Telefon", 'client_id' => $client->id])
            ->assertSessionHas('status', trans_choice('task.added_n', 3, ['n' => 3]));
        $this->assertSame(3, Ticket::where('client_id', $client->id)->count());

        $this->post(route('tasks.sets.store'), ['name' => 'Kampania', 'body' => "Brief +0 1h\nGrafiki +3 @ola\nRaport +30"])->assertRedirect();
        $set = \App\Models\TaskSet::first();
        $this->post(route('tasks.sets.apply', $set), ['client_id' => $client->id, 'start_on' => '2026-12-01', 'campaign_id' => $campaign->id])->assertRedirect();
        $made = Ticket::where('campaign_id', $campaign->id)->orderBy('id')->get();
        $this->assertSame(['Brief', 'Grafiki', 'Raport'], $made->pluck('title')->all());
        $this->assertSame(['2026-12-01', '2026-12-04', '2026-12-31'], $made->map(fn ($t) => $t->due_on->toDateString())->all());
        $this->assertSame(1, $set->fresh()->uses);

        // Kampania innego klienta jest odrzucana.
        $other = Client::factory()->create();
        $this->post(route('tasks.sets.apply', $set), ['client_id' => $other->id, 'start_on' => '2026-12-01', 'campaign_id' => $campaign->id])->assertSessionHasErrors('campaign_id');
    }

    public function test_campaign_creation_inserts_task_set(): void
    {
        $this->admin();
        $client = Client::factory()->create(['name' => 'FitLife']);
        $set = \App\Models\TaskSet::create(['name' => 'Nowa kampania social', 'body' => "Brief +0 1h\nGrafiki +3\nRaport +30"]);

        $this->get(route('social.campaigns.create'))->assertOk()->assertSee(__('task.campaign_set'))->assertSee('Nowa kampania social');
        $this->post(route('social.campaigns.store'), ['client_id' => $client->id, 'name' => 'Zima', 'budget' => 1000, 'status' => 'draft',
            'starts_on' => '2026-12-10', 'task_set_id' => $set->id])->assertRedirect()->assertSessionHas('status');
        $campaign = \App\Models\Campaign::where('name', 'Zima')->first();
        $made = Ticket::where('campaign_id', $campaign->id)->orderBy('id')->get();
        $this->assertSame(['2026-12-10', '2026-12-13', '2027-01-09'], $made->map(fn ($t) => $t->due_on->toDateString())->all());

        // Bez zestawu — bez zadań.
        $this->post(route('social.campaigns.store'), ['client_id' => $client->id, 'name' => 'Lato', 'budget' => 0, 'status' => 'draft', 'task_set_id' => '']);
        $this->assertSame(0, Ticket::where('campaign_id', \App\Models\Campaign::where('name', 'Lato')->value('id'))->count());

        // Z zakładki Ustawienia i filtr zadań kampanii.
        $lato = \App\Models\Campaign::where('name', 'Lato')->first();
        $this->post(route('social.campaigns.task-set', $lato), ['task_set_id' => $set->id, 'start_on' => '2027-06-01'])->assertSessionHas('status');
        $this->assertSame(3, Ticket::where('campaign_id', $lato->id)->count());
        $this->get(route('tasks.index', ['who' => 'all', 'campaign' => $lato->id]))->assertOk()
            ->assertViewHas('groups', fn ($g) => collect($g)->flatten()->count() === 3);
        $this->get(route('social.campaigns.index', ['c' => $lato->id, 'tab' => 'settings']))->assertOk()->assertSee(__('task.campaign_set'));
    }
}
