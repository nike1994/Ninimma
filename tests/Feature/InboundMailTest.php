<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketMessagePosted;
use App\Notifications\TicketReceivedByEmail;
use App\Services\InboundMail;
use App\Services\TicketService;
use App\Support\Mail\MimeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InboundMailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agency.inbound.address' => 'zgloszenia@agencja.test', 'mail.from.address' => 'panel@agencja.test']);
        $this->admin = User::factory()->admin()->create(['email' => 'wiktoria@agencja.test']);
        $this->client = User::factory()->create(['email' => 'Anna@Klient.test', 'client_id' => Client::factory()->create()->id]);
        $this->ticket = app(TicketService::class)->create($this->client, ['title' => 'Strona główna'], 'Opis');
        $this->ticket->forceFill(['status' => TicketStatus::WaitingClient])->save();
    }

    private function eml(array $headers, string $body, ?string $contentType = 'text/plain; charset=UTF-8'): string
    {
        $headers += ['Message-ID' => '<'.uniqid('m', true).'@mail.test>', 'Date' => now()->toRfc2822String(), 'MIME-Version' => '1.0'];
        if ($contentType) {
            $headers += ['Content-Type' => $contentType];
        }
        $h = '';
        foreach ($headers as $k => $v) {
            $h .= "{$k}: {$v}\r\n";
        }

        return $h."\r\n".$body;
    }

    public function test_reply_to_notification_is_added_to_ticket_without_quote_and_with_attachment(): void
    {
        Notification::fake();
        $token = InboundMail::token($this->ticket);
        $pdf = base64_encode('%PDF-1.4 testowy plik');
        $raw = $this->eml([
            'From' => '"Anna Kowalska" <anna@klient.test>',
            'To' => "zgloszenia+{$token}@agencja.test",
            'Subject' => '=?UTF-8?B?'.base64_encode('Odp: [#'.$this->ticket->id.'] Nowa wiadomość').'?=',
            'In-Reply-To' => '<abc@agencja.test>',
        ], "--B1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            ."Dzi=C4=99kuj=C4=99, wysy=C5=82am poprawki w za=C5=82=C4=85czniku.\r\n\r\nAnna\r\n\r\n"
            ."W dniu 26.11.2026 o 10:00, Ninimma <panel@agencja.test> pisze:\r\n> ##- Odpowiedz nad t=C4=85 lini=C4=85 -##\r\n> Stara tre=C5=9B=C4=87\r\n"
            ."--B1\r\nContent-Type: application/pdf; name=\"poprawki.pdf\"\r\nContent-Disposition: attachment; filename*=UTF-8''popr%C3%B3wki.pdf\r\nContent-Transfer-Encoding: base64\r\n\r\n{$pdf}\r\n--B1--\r\n",
            'multipart/mixed; boundary="B1"');

        $row = app(InboundMail::class)->handle($raw);
        $this->assertSame('accepted_reply', $row->status);
        $this->assertSame($this->ticket->id, $row->ticket_id);
        $msg = $this->ticket->messages()->latest('id')->first();
        $this->assertSame("Dziękuję, wysyłam poprawki w załączniku.\n\nAnna", $msg->body);
        $this->assertSame($this->client->id, $msg->user_id);
        $this->assertFalse($msg->is_internal);
        $this->assertSame(['poprówki.pdf'], $msg->attachments->pluck('name')->all());
        Notification::assertSentTo($this->admin, TicketMessagePosted::class);

        // Ten sam e-mail drugi raz (np. ponowne pobranie ze skrzynki) — bez duplikatu.
        $this->assertNull(app(InboundMail::class)->handle($raw));
        $this->assertSame(2, $this->ticket->messages()->count());
    }

    public function test_new_email_from_client_creates_ticket_and_confirms(): void
    {
        Notification::fake();
        $raw = $this->eml(['From' => 'anna@klient.test', 'To' => 'zgloszenia@agencja.test', 'Subject' => 'Fwd: Re: Nie działa formularz kontaktowy'],
            "<html><body><p>Formularz na stronie nie wysyła wiadomości.</p><p>Pozdrawiam</p></body></html>", 'text/html; charset=UTF-8');
        $row = app(InboundMail::class)->handle($raw);
        $this->assertSame('accepted_new', $row->status);
        $t = Ticket::find($row->ticket_id);
        $this->assertSame('Nie działa formularz kontaktowy', $t->title);
        $this->assertSame($this->client->client_id, $t->client_id);
        $this->assertStringContainsString('Formularz na stronie nie wysyła wiadomości.', $t->messages()->first()->body);
        Notification::assertSentTo($this->client, TicketReceivedByEmail::class);
    }

    public function test_unknown_spoofed_automatic_and_foreign_ticket_emails_are_rejected(): void
    {
        $other = app(TicketService::class)->create(User::factory()->create(['client_id' => Client::factory()->create()->id]), ['title' => 'Cudze'], 'x');
        $svc = app(InboundMail::class);
        $before = DB::table('ticket_messages')->count();

        $this->assertSame('rejected_sender', $svc->handle($this->eml(['From' => 'obcy@spam.test', 'To' => 'zgloszenia@agencja.test', 'Subject' => 'Hej'], 'Kup teraz'))->status);
        $this->assertSame('ignored_auto', $svc->handle($this->eml(['From' => 'anna@klient.test', 'Auto-Submitted' => 'auto-replied', 'Subject' => 'Poza biurem'], 'Wracam w poniedziałek'))->status);
        $this->assertSame('ignored_auto', $svc->handle($this->eml(['From' => 'MAILER-DAEMON@klient.test', 'Subject' => 'Undelivered'], 'x'))->status);
        $this->assertSame('rejected_auth', $svc->handle($this->eml(['From' => 'anna@klient.test', 'Authentication-Results' => 'mx.agencja.test; spf=fail; dkim=none; dmarc=fail', 'Subject' => 'x'], 'x'))->status);
        // Ważny znacznik, ale cudzego zlecenia.
        $this->assertSame('rejected_ticket', $svc->handle($this->eml(['From' => 'anna@klient.test', 'To' => 'zgloszenia+'.InboundMail::token($other).'@agencja.test', 'Subject' => 'Re: x'], 'Wpis'))->status);
        // Podrobiony podpis znacznika nie trafia do zlecenia (zakłada nowe, jak zwykły e-mail).
        $forged = $svc->handle($this->eml(['From' => 'anna@klient.test', 'To' => 'zgloszenia+t'.$other->id.'-0000000000@agencja.test', 'Subject' => 'Re: x'], 'Wpis'));
        $this->assertSame('accepted_new', $forged->status);
        $this->assertNotSame($other->id, $forged->ticket_id);
        $this->assertSame(0, $other->messages()->where('body', 'Wpis')->count());
        $this->assertSame($before + 1, DB::table('ticket_messages')->count());
    }

    public function test_client_can_reply_with_ticket_number_in_subject_and_limit_applies(): void
    {
        config(['agency.inbound.max_per_hour' => 2]);
        $svc = app(InboundMail::class);
        $mk = fn ($i) => $this->eml(['From' => 'anna@klient.test', 'Subject' => 'RE: [#'.$this->ticket->id.'] Strona'], "Odpowiedź {$i}\n\n-- \nAnna Kowalska\ntel. 600 000 000");
        $this->assertSame('accepted_reply', $svc->handle($mk(1))->status);
        $this->assertSame('Odpowiedź 1', $this->ticket->messages()->latest('id')->first()->body);
        $this->assertSame('accepted_reply', $svc->handle($mk(2))->status);
        $this->assertSame('rate_limited', $svc->handle($mk(3))->status);
    }

    public function test_notification_email_carries_reply_address_marker_and_reference(): void
    {
        $msg = app(TicketService::class)->postMessage($this->ticket, $this->admin, 'Gotowe, prosimy o akceptację', false);
        $mail = (new TicketMessagePosted($msg))->toMail($this->client);
        $token = InboundMail::token($this->ticket);
        $this->assertSame("zgloszenia+{$token}@agencja.test", $mail->replyTo[0][0]);
        $this->assertStringContainsString('##-', $mail->introLines[0]);
        $this->assertStringContainsString($token, implode(' ', $mail->outroLines));

        config(['agency.inbound.plus_addressing' => false]);
        $this->assertSame('zgloszenia@agencja.test', InboundMail::replyAddress($this->ticket));
    }

    public function test_mime_parser_handles_polish_charsets_and_outlook_quotes(): void
    {
        $raw = "From: =?ISO-8859-2?Q?Pawe=B3_Kot?= <pawel@x.test>\nSubject: =?ISO-8859-2?Q?Zg=B3oszenie?=\nContent-Type: text/plain; charset=ISO-8859-2\nContent-Transfer-Encoding: quoted-printable\n\nPro=B6ba o zmian=EA.\n\nOd: Ninimma <panel@agencja.test>\nWys=B3ano: czwartek\nTemat: x\n\nstare";
        $m = MimeMessage::parse($raw);
        $this->assertSame('Zgłoszenie', $m->subject());
        $this->assertSame(['email' => 'pawel@x.test', 'name' => 'Paweł Kot'], $m->from());
        $this->assertSame('Prośba o zmianę.', InboundMail::cleanReply($m->plainText()));
        $this->assertSame('Zmiana logo', InboundMail::cleanSubject('Odp: RE: Fwd: [#12] Zmiana logo'));
    }
}
