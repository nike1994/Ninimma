<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\FileUsage;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReceivedByEmail;
use App\Support\Mail\MimeMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Zgłoszenia przez e-mail: odpowiedź na powiadomienie dopisuje się do zlecenia, nowy e-mail klienta zakłada zlecenie.
 * Wiadomość przyjmujemy tylko od aktywnego użytkownika panelu, z podpisanym znacznikiem zlecenia (albo [#id] swojego
 * zlecenia), bez automatycznych odpowiedzi i z limitem na nadawcę. Treść cytatu i podpisu jest obcinana.
 */
class InboundMail
{
    public function __construct(private TicketService $tickets, private FileStore $files) {}

    public static function enabled(): bool
    {
        return filled(config('agency.inbound.address'));
    }

    /** Podpisany znacznik zlecenia, np. t12-3fa9c01b7d. */
    public static function token(Ticket $ticket): string
    {
        return 't'.$ticket->id.'-'.substr(hash_hmac('sha256', 'ticket-mail:'.$ticket->id, (string) config('app.key')), 0, 10);
    }

    public static function verify(string $id, string $sig): bool
    {
        return hash_equals(substr(hash_hmac('sha256', 'ticket-mail:'.$id, (string) config('app.key')), 0, 10), strtolower($sig));
    }

    /** Adres „Odpowiedz do” dla zlecenia (z plusem, gdy skrzynka to obsługuje). */
    public static function replyAddress(Ticket $ticket): ?string
    {
        $addr = config('agency.inbound.address');
        if (! $addr) {
            return null;
        }
        if (! config('agency.inbound.plus_addressing')) {
            return $addr;
        }
        [$local, $domain] = explode('@', $addr, 2);

        return $local.'+'.self::token($ticket).'@'.$domain;
    }

    public static function referenceId(Ticket $ticket): string
    {
        return self::token($ticket).'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ninimma.local');
    }

    /**
     * Powiadomienie o zleceniu, na które można odpowiedzieć: znacznik nad cytatem, Reply-To ze znacznikiem,
     * nagłówek References (wątek w programie pocztowym) i znacznik w stopce.
     */
    public static function decorate(\Illuminate\Notifications\Messages\MailMessage $mail, Ticket $ticket): \Illuminate\Notifications\Messages\MailMessage
    {
        if (! self::enabled()) {
            return $mail;
        }
        $ref = self::referenceId($ticket);
        $mail->replyTo(self::replyAddress($ticket))
            ->withSymfonyMessage(function ($m) use ($ref) {
                $m->getHeaders()->addTextHeader('References', '<'.$ref.'>');
                $m->getHeaders()->addTextHeader('X-Ninimma-Ticket', $ref);
            });
        // Znacznik na początku: wszystko pod nim (cytat) jest obcinane z odpowiedzi.
        array_unshift($mail->introLines, __('mail.reply_above'));
        $mail->outroLines[] = __('mail.reply_hint').' · Ref: '.self::token($ticket);

        return $mail;
    }

    /** Przetwarza jedną surową wiadomość. Zwraca wpis dziennika (albo null dla duplikatu). */
    public function handle(string $raw): ?object
    {
        $mail = MimeMessage::parse($raw);
        $messageId = trim((string) $mail->header('message-id'), " <>") ?: 'sha1:'.sha1($raw);
        if (DB::table('inbound_emails')->where('message_id', Str::limit($messageId, 250, ''))->exists()) {
            return null;
        }
        $from = $mail->from()['email'];
        $log = ['message_id' => Str::limit($messageId, 250, ''), 'from_email' => Str::limit($from, 250, ''),
            'subject' => Str::limit($mail->subject(), 250, ''), 'created_at' => now()];

        try {
            $result = $this->process($mail, $from);
        } catch (\Throwable $e) {
            report($e);
            $result = ['status' => 'error', 'note' => Str::limit($e->getMessage(), 250, '')];
        }
        $row = $log + $result + ['user_id' => null, 'ticket_id' => null, 'ticket_message_id' => null, 'attachments' => 0, 'note' => null];
        $row['id'] = DB::table('inbound_emails')->insertGetId($row);
        if (! str_starts_with($row['status'], 'accepted')) {
            SystemLog::record('inbound_mail', "Inbound e-mail {$row['status']}", ['from' => $from, 'note' => $row['note']], $row['status'] === 'error' ? 'error' : 'info');
        }

        return (object) $row;
    }

    private function process(MimeMessage $mail, string $from): array
    {
        if ($this->isAutomatic($mail, $from)) {
            return ['status' => 'ignored_auto'];
        }
        $auth = strtolower((string) $mail->header('authentication-results'));
        if (str_contains($auth, 'dmarc=fail') || (str_contains($auth, 'spf=fail') && ! str_contains($auth, 'dkim=pass'))) {
            return ['status' => 'rejected_auth', 'note' => 'SPF/DMARC'];
        }
        $user = User::whereRaw('lower(email) = ?', [$from])->where('is_active', true)->first();
        if (! $user) {
            return ['status' => 'rejected_sender'];
        }
        $recent = DB::table('inbound_emails')->where('from_email', $from)->where('created_at', '>', now()->subHour())->count();
        if ($recent >= config('agency.inbound.max_per_hour', 30)) {
            return ['status' => 'rate_limited', 'user_id' => $user->id];
        }

        $ticket = $this->findTicket($mail, $user);
        $body = self::cleanReply($mail->plainText());
        $base = ['user_id' => $user->id];

        if ($ticket === false) {
            return $base + ['status' => 'rejected_ticket', 'note' => 'no access'];
        }
        if ($ticket === null) {
            if ($user->isAdmin() || ! config('agency.inbound.allow_new_tickets')) {
                return $base + ['status' => 'rejected_ticket', 'note' => 'no ticket reference'];
            }
            [$uploads, $count] = $this->storeAttachments($mail, $user);
            $title = self::cleanSubject($mail->subject()) ?: Str::limit($body, 80) ?: __('mail.inbound_untitled');
            $ticket = $this->tickets->create($user, ['title' => Str::limit($title, 200, '')], $body, $uploads);
            $user->notify(new TicketReceivedByEmail($ticket));

            return $base + ['status' => 'accepted_new', 'ticket_id' => $ticket->id, 'ticket_message_id' => $ticket->last_message_id, 'attachments' => $count];
        }

        [$uploads, $count] = $this->storeAttachments($mail, $user);
        if ($body === '' && ! $uploads) {
            return $base + ['status' => 'ignored_empty', 'ticket_id' => $ticket->id];
        }
        $message = $this->tickets->postMessage($ticket, $user, $body, false, $uploads);

        return $base + ['status' => 'accepted_reply', 'ticket_id' => $ticket->id, 'ticket_message_id' => $message->id, 'attachments' => $count];
    }

    /** Zlecenie z podpisanego znacznika albo [#id] w temacie (tylko zlecenie klienta nadawcy). null = brak, false = brak dostępu. */
    private function findTicket(MimeMessage $mail, User $user): Ticket|false|null
    {
        $haystack = implode(' ', array_merge($mail->recipients(), $mail->headerAll('in-reply-to'), $mail->headerAll('references'), [$mail->plainText()]));
        $ticket = null;
        if (preg_match_all('/\bt(\d+)-([a-f0-9]{10})\b/i', $haystack, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (self::verify($m[1], $m[2])) {
                    $ticket = Ticket::find((int) $m[1]);

                    break;
                }
            }
        }
        if (! $ticket && ! $user->isAdmin() && preg_match('/\[#(\d+)\]/', $mail->subject(), $m)) {
            $ticket = Ticket::where('id', (int) $m[1])->where('client_id', $user->client_id)->first();
        }
        if (! $ticket) {
            return null;
        }
        $allowed = ($user->isAdmin() || $ticket->client_id === $user->client_id) && $ticket->archive_state === 'active';

        return $allowed ? $ticket : false;
    }

    private function isAutomatic(MimeMessage $mail, string $from): bool
    {
        $auto = strtolower((string) $mail->header('auto-submitted'));
        $prec = strtolower((string) $mail->header('precedence'));
        $own = array_filter([strtolower((string) config('mail.from.address')), strtolower((string) config('agency.inbound.address'))]);

        return ($auto !== '' && $auto !== 'no')
            || in_array($prec, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || $mail->header('x-autoreply') !== null || $mail->header('x-autorespond') !== null
            || $mail->header('list-id') !== null
            || str_contains(strtolower((string) $mail->header('content-type')), 'multipart/report')
            || preg_match('/^(mailer-daemon|postmaster|no-?reply|bounce)/i', $from)
            || in_array($from, $own, true);
    }

    /** @return array{0: list<int>, 1: int} */
    private function storeAttachments(MimeMessage $mail, User $user): array
    {
        $ids = [];
        $maxMb = $user->isAdmin() ? 0 : (int) config('agency.client_max_file_mb');
        foreach (array_slice($mail->attachments, 0, 20) as $a) {
            $size = strlen($a['content']);
            // Pomijamy obrazki ze stopki (image001.png itp.) i pliki ponad limit klienta.
            if ((preg_match('/^(image|outlook)\d*\.(png|jpe?g|gif)$/i', $a['name']) && $size < 150 * 1024) || ($maxMb && $size > $maxMb * 1024 * 1024)) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'mailin');
            file_put_contents($tmp, $a['content']);
            $file = $this->files->storeLocalFile($tmp, $a['mime']);
            @unlink($tmp);
            $name = Str::limit(preg_replace('/[\\\\\/\x00-\x1f]+/', '_', $a['name']), 200, '');
            $ids[] = FileUsage::create(['file_id' => $file->id, 'name' => $name, 'uploaded_by' => $user->id])->id;
        }

        return [$ids, count($ids)];
    }

    public static function cleanSubject(string $s): string
    {
        $s = preg_replace('/\[#\d+\]/', '', $s);
        do {
            $before = $s;
            $s = preg_replace('/^\s*(re|odp|fwd?|pd|aw|wg|sv|tr)\s*(\[\d+\])?\s*:\s*/i', '', (string) $s);
        } while ($s !== $before);

        return trim((string) $s);
    }

    /** Sama odpowiedź: bez cytatu, historii i podpisu. */
    public static function cleanReply(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $cuts = [
            '/^.*##-.*-##.*$/m',                                                     // nasz znacznik w powiadomieniu
            '/^On .{4,300}wrote:\s*$/m',
            '/^W dniu .{4,300}(pisze|napisał\(a\)|napisała|napisał)\s*:\s*$/mu',
            '/^Wiadomość napisana przez .{2,300}:\s*$/mu',
            '/^-{2,}\s*(Original Message|Wiadomość oryginalna|Oryginalna wiadomość|Forwarded message|Przekazana wiadomość).*$/miu',
            '/^_{10,}\s*$/m',
            '/^(From|Od):\s.+\n(Sent|Wysłano|Date|Data|To|Do):/miu',
            '/^-- $/m',                                                               // podpis
            '/^(Sent from my|Wysłano z mojego|Wysłane z) .{0,60}$/miu',
        ];
        foreach ($cuts as $re) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE)) {
                $text = substr($text, 0, $m[0][1]);
            }
        }
        $lines = array_filter(explode("\n", $text), fn ($l) => ! str_starts_with(ltrim($l), '>'));
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim((string) $text);
    }
}
