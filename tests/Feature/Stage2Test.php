<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\CostDocument;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Stage2Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    private function el(string $id, int $version, array $extra = []): array
    {
        return array_merge(['id' => $id, 'type' => 'rectangle', 'version' => $version, 'versionNonce' => rand(), 'x' => 0, 'y' => 0, 'isDeleted' => false], $extra);
    }

    public function test_board_sync_merges_by_element_version(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $this->post(route('boards.store', $ticket), ['name' => 'Zależności'])->assertRedirect();
        $board = Board::first();

        $r = $this->postJson(route('boards.push', $board), ['elements' => [$this->el('a', 1), $this->el('b', 1)], 'since' => 0])->assertOk()->json();
        $this->assertSame(1, $r['revision']);
        $this->assertSame('admin', $board->fresh()->elements['a']['customData']['side']);

        // Klient: starsza wersja ignorowana, nowsza przyjęta; strona elementu się nie zmienia.
        $this->actingAs($client)->postJson(route('boards.push', $board), ['elements' => [$this->el('a', 1, ['x' => 99]), $this->el('b', 3, ['x' => 50]), $this->el('c', 1)], 'since' => 1])->assertOk();
        $board->refresh();
        $this->assertSame(0, $board->elements['a']['x']);
        $this->assertSame(50, $board->elements['b']['x']);
        $this->assertSame('admin', $board->elements['b']['customData']['side']);
        $this->assertSame('client', $board->elements['c']['customData']['side']);

        // Odpytywanie zwraca tylko zmiany od podanego numeru.
        $since = $this->actingAs($client)->getJson(route('boards.poll', ['board' => $board, 'since' => 1]))->json('elements');
        $this->assertEqualsCanonicalizing(['b', 'c'], array_column($since, 'id'));
        $this->assertContains($client->name, $this->actingAs($admin)->getJson(route('boards.poll', $board))->json('presence'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->getJson(route('boards.poll', $board))->assertForbidden();
    }

    public function test_versions_dot_changes_and_restore(): void
    {
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $service = app(BoardService::class);
        $board = $service->create($ticket, $admin, 'Plan');
        $service->merge($board, $admin, [$this->el('a', 1)]);
        $v1 = $service->saveVersion($board, $admin, 'Pierwsza');
        $this->assertTrue($board->fresh()->hasUnseenVersionFor($client));
        $this->assertTrue($ticket->fresh()->hasUnreadFor($client)); // kropka przy zleceniu

        $service->markSeen($board, $client);
        $this->assertFalse($board->fresh()->hasUnseenVersionFor($client));
        $service->merge($board, $admin, [$this->el('a', 2, ['x' => 10]), $this->el('b', 1)]);
        $changes = $service->changesFor($board->fresh(), $client);
        $this->assertSame(['b'], array_column($changes['added'], 'id'));
        $this->assertSame(['a'], array_column($changes['changed'], 'id'));

        // Automatyczna wersja tylko gdy są zmiany.
        $this->assertNotNull($service->saveVersion($board, $admin, null, true));
        $this->assertNull($service->saveVersion($board, $admin, null, true));

        $this->post(route('boards.versions.restore', [$board, $v1]))->assertRedirect();
        $live = collect($board->fresh()->liveElements());
        $this->assertSame(['a'], $live->pluck('id')->all());
        $this->assertSame(0, $live->first()['x']);

        $this->actingAs($client)->get(route('boards.show', $board))->assertOk();
        $this->actingAs($client)->get(route('boards.versions.show', [$board, $v1]))->assertOk();
    }

    public function test_board_image_upload(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $board = app(BoardService::class)->create(Ticket::factory()->create(), $admin, 'Zrzuty');
        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->postJson(route('boards.files.store', $board), ['id' => 'f1', 'dataURL' => $png])->assertOk();
        $this->get(route('boards.files.show', [$board, 'f1']))->assertOk();
        $this->postJson(route('boards.files.store', $board), ['id' => 'f2', 'dataURL' => 'data:text/html;base64,PGI+'])->assertStatus(422);
    }

    public function test_quote_limit_decision_and_handover(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);

        $this->post(route('admin.tickets.quotes.store', $ticket), ['hours_limit' => 1, 'justification' => 'Diagnoza', 'is_diagnosis' => 1])->assertRedirect();
        $this->assertSame('needs_decision', $ticket->fresh()->status->value);
        $q1 = $ticket->quotes()->first();
        $this->actingAs($client)->post(route('quotes.decide', $q1), ['decision' => 'accept'])->assertRedirect();
        $this->assertSame(60, $q1->fresh()->acceptedMinutes());
        $this->assertSame('with_agency', $ticket->fresh()->status->value);

        // Zmiana zakresu z opcjami; poprzednia wycena zostaje w historii.
        $this->actingAs($admin)->post(route('admin.tickets.quotes.store', $ticket), ['justification' => 'Większy problem', 'options' => [
            ['label' => 'Pełna naprawa', 'hours' => 5, 'amount' => 1000], ['label' => 'Obejście', 'hours' => 2], ['label' => ''],
        ]]);
        $q2 = $ticket->quotes()->where('version', 2)->first();
        $this->assertCount(2, $q2->options);
        $this->actingAs($client)->post(route('quotes.decide', $q2), ['decision' => 'accept'])->assertStatus(422); // trzeba wybrać opcję
        $this->actingAs($client)->post(route('quotes.decide', $q2), ['decision' => 'accept', 'option' => 1])->assertRedirect();
        $this->assertSame(120, $ticket->fresh()->acceptedQuote()->acceptedMinutes());

        // 80% limitu → ostrzeżenie.
        $this->actingAs($admin)->post(route('admin.tickets.time', $ticket), ['hours' => '1:40', 'spent_on' => today()->toDateString()])
            ->assertSessionHas('status', __('ui.limit_warning', ['pct' => 83]));
        $this->assertTrue($ticket->events()->where('type', 'limit_warning')->exists());

        $this->post(route('admin.tickets.handover', $ticket))->assertRedirect();
        $this->actingAs($client)->post(route('tickets.handover.accept', $ticket))->assertRedirect();
        $this->assertSame('done', $ticket->fresh()->status->value);
        $this->assertNotNull($ticket->fresh()->accepted_at);
        $this->actingAs($client)->get(route('tickets.show', $ticket))->assertOk();
    }

    public function test_terms_must_be_accepted_and_billing_isolated(): void
    {
        $admin = $this->admin();
        $this->put(route('admin.terms.edit'), ['terms' => 'Regulamin v1'])->assertRedirect();
        $this->assertSame(1, Setting::termsVersion());

        $ticket = Ticket::factory()->create();
        $client = User::factory()->create(['client_id' => $ticket->client_id]);
        $this->actingAs($client)->get(route('dashboard'))->assertRedirect(route('terms.show'));
        $this->actingAs($client)->post(route('terms.accept'), ['accept' => 1])->assertRedirect();
        $this->actingAs($client)->get(route('dashboard'))->assertOk();

        $this->actingAs($admin)->put(route('admin.terms.edit'), ['terms' => 'Regulamin v2', 'new_version' => 1]);
        $this->actingAs($client)->get(route('dashboard'))->assertRedirect(route('terms.show'));
        $this->actingAs($client)->post(route('terms.accept'), ['accept' => 1]);

        $this->actingAs($admin)->post(route('admin.cost-documents.store'), ['client_id' => $ticket->client_id, 'number' => 'FV/1/2026', 'amount' => 1230, 'issued_on' => today()->toDateString()])->assertRedirect();
        CostDocument::create(['client_id' => User::factory()->create()->client_id, 'number' => 'FV/OBCA', 'amount' => 1, 'issued_on' => today()]);
        $this->actingAs($client)->get(route('billing.index'))->assertOk()->assertSee('FV/1/2026')->assertDontSee('FV/OBCA');
        $doc = CostDocument::where('number', 'FV/1/2026')->first();
        $this->actingAs($client)->patch(route('admin.cost-documents.paid', $doc))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.cost-documents.paid', $doc));
        $this->assertTrue($doc->fresh()->is_paid);
    }
}
