<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_threads_unread_and_isolation(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Wiktoria Admin']);
        $anna = User::factory()->create(['name' => 'Anna Kowalska']);
        $other = User::factory()->create();
        $svc = app(TicketService::class);
        $t = $svc->create($anna, ['title' => 'Zmiana banera'], 'Prośba o zmianę banera na stronie głównej');
        $foreign = $svc->create($other, ['title' => 'Cudze zlecenie'], 'Tajne');
        $svc->postMessage($t, $anna, 'Czy da się to zrobić do piątku?', false);
        $svc->postMessage($t, $admin, 'Notatka tylko dla zespołu', true);
        \App\Models\Read::where('user_id', $admin->id)->delete(); // agencja jeszcze nie czytała

        // Agencja: obie rozmowy, ostatnia wiadomość to notatka wewnętrzna.
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);
        $d = $this->getJson(route('chat.threads'))->assertOk()->json();
        $this->assertEqualsCanonicalizing(['Zmiana banera', 'Cudze zlecenie'], array_column($d['threads'], 'title'));
        $row = collect($d['threads'])->firstWhere('id', $t->id);
        $this->assertTrue($row['last']['internal']);
        $this->assertSame($anna->client->name, $row['client']);
        $this->assertTrue($row['unread']);
        $this->assertSame(2, $this->getJson(route('chat.threads', ['count' => 1]))->json('unread_total'));

        // Otwarcie rozmowy (poll) oznacza ją jako przeczytaną.
        $this->getJson(route('tickets.poll', $t).'?after=0')->assertOk();
        $this->assertFalse(collect($this->getJson(route('chat.threads'))->json('threads'))->firstWhere('id', $t->id)['unread']);
        $this->assertSame([$foreign->id], array_column($this->getJson(route('chat.threads', ['filter' => 'unread']))->json('threads'), 'id'));

        // Klient: tylko swoja rozmowa, bez notatki wewnętrznej w podglądzie i bez nazwy klienta.
        $this->actingAs($anna);
        $d = $this->getJson(route('chat.threads'))->assertOk()->json();
        $this->assertSame(['Zmiana banera'], array_column($d['threads'], 'title'));
        $this->assertStringNotContainsString('Notatka tylko', json_encode($d));
        $this->assertNull($d['threads'][0]['client']);
        $this->getJson(route('tickets.poll', $foreign).'?after=0')->assertForbidden();

        // Odpowiedź z okna czatu (JSON).
        $this->postJson(route('tickets.messages.store', $t), ['body' => 'Dziękuję!'])->assertOk()->assertJsonStructure(['id']);
        $this->assertSame('Dziękuję!', $t->messages()->latest('id')->value('body'));
    }

    public function test_dock_on_every_page_and_separate_window(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);
        $this->get(route('tasks.index'))->assertOk()->assertSee('data-chat', false)->assertSee('js/chat.js', false);
        $this->get(route('chat.window'))->assertOk()->assertSee('chat-full', false)->assertDontSee('data-chat-toggle', false);
        auth()->logout();
        $this->get(route('chat.window'))->assertRedirect(route('login'));
    }
}
