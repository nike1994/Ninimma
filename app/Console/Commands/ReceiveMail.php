<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use App\Services\InboundMail;
use App\Support\Mail\ImapClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Odbiór e-maili do zleceń.
 *   php artisan agency:mail-in --imap        — skrzynka IMAP (cron co minutę, ustawiony w routes/console.php)
 *   php artisan agency:mail-in < wiadomosc   — przekierowanie poczty do programu („pipe” w panelu hostingu)
 */
class ReceiveMail extends Command
{
    protected $signature = 'agency:mail-in {--imap : Pobierz nieprzeczytane wiadomości ze skrzynki IMAP} {--limit=25 : Najwięcej wiadomości na jedno uruchomienie}';

    protected $description = 'Zgłoszenia i odpowiedzi przez e-mail';

    public function handle(InboundMail $inbound): int
    {
        if (! InboundMail::enabled()) {
            $this->warn('AGENCY_INBOUND_ADDRESS nie jest ustawiony.');

            return self::SUCCESS;
        }
        if (! $this->option('imap')) {
            $raw = stream_get_contents(STDIN);
            if (trim((string) $raw) === '') {
                $this->error('Brak wiadomości na wejściu.');

                return self::FAILURE;
            }
            $row = $inbound->handle($raw);
            $this->line($row ? $row->status : 'duplicate');

            return self::SUCCESS; // zawsze 0 — inaczej serwer poczty odeśle nadawcy błąd
        }

        $c = config('agency.inbound');
        if (! $c['imap_host'] || ! $c['imap_user']) {
            $this->warn('Brak ustawień IMAP (AGENCY_IMAP_*).');

            return self::SUCCESS;
        }
        $imap = new ImapClient($c['imap_host'], $c['imap_port'], $c['imap_encryption']);
        $n = 0;
        try {
            $imap->connect($c['imap_user'], (string) $c['imap_password']);
            $imap->select($c['imap_folder']);
            foreach (array_slice($imap->unseen(), 0, (int) $this->option('limit')) as $uid) {
                $row = $inbound->handle($imap->fetchRaw($uid));
                // Przetworzoną wiadomość oznaczamy jako przeczytaną (albo przenosimy), żeby nie wróciła.
                $c['imap_done_folder'] ? $imap->move($uid, $c['imap_done_folder']) : $imap->markSeen($uid);
                $n++;
                $this->line($uid.': '.($row ? $row->status : 'duplicate'));
            }
            $imap->logout();
            Cache::forever('agency:last_mail_in', now()->toIso8601String());
            Cache::forget('agency:mail_in_error');
        } catch (\Throwable $e) {
            $imap->logout();
            // Ten sam błąd zapisujemy raz na godzinę, żeby nie zapchać dziennika.
            if (! Cache::has('agency:mail_in_error')) {
                SystemLog::record('inbound_mail', 'IMAP: '.$e->getMessage(), [], 'error');
            }
            Cache::put('agency:mail_in_error', $e->getMessage(), now()->addHour());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
