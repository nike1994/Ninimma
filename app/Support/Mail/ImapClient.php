<?php

namespace App\Support\Mail;

use RuntimeException;

/**
 * Minimalny klient IMAP (bez rozszerzenia php-imap, którego nowe PHP nie ma):
 * logowanie, nieprzeczytane wiadomości, pobranie surowej treści, oznaczenie jako przeczytane / przeniesienie.
 */
class ImapClient
{
    /** @var resource|null */
    private $socket = null;

    private int $tag = 0;

    public function __construct(private string $host, private int $port = 993, private string $encryption = 'ssl', private int $timeout = 20) {}

    public function connect(string $user, string $password): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->host]]);
        $this->socket = @stream_socket_client($scheme.$this->host.':'.$this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (! $this->socket) {
            throw new RuntimeException("IMAP: brak połączenia z {$this->host}:{$this->port} ({$errstr})");
        }
        stream_set_timeout($this->socket, $this->timeout);
        $this->line(); // powitanie
        if ($this->encryption === 'tls') {
            $this->command('STARTTLS');
            if (! stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('IMAP: STARTTLS nie powiódł się');
            }
        }
        $this->command('LOGIN '.$this->quote($user).' '.$this->quote($password));
    }

    public function select(string $folder = 'INBOX'): void
    {
        $this->command('SELECT '.$this->quote($folder));
    }

    /** @return list<int> UID nieprzeczytanych wiadomości */
    public function unseen(): array
    {
        $lines = $this->command('UID SEARCH UNSEEN');
        foreach ($lines as $l) {
            if (str_starts_with($l, '* SEARCH')) {
                return array_map('intval', array_filter(explode(' ', trim(substr($l, 8)))));
            }
        }

        return [];
    }

    /** Surowa wiadomość (BODY.PEEK nie zmienia flagi przeczytania). */
    public function fetchRaw(int $uid): string
    {
        $tag = $this->send("UID FETCH {$uid} (BODY.PEEK[])");
        $raw = '';
        while (true) {
            $line = $this->line();
            if (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $raw = $this->read((int) $m[1]);

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                $this->assertOk($line);

                break;
            }
        }

        return $raw;
    }

    public function markSeen(int $uid): void
    {
        $this->command("UID STORE {$uid} +FLAGS.SILENT (\\Seen)");
    }

    /** Przeniesienie do folderu (np. „Ninimma”); gdy serwer nie umie MOVE — kopia + usunięcie. */
    public function move(int $uid, string $folder): void
    {
        try {
            $this->command("UID MOVE {$uid} ".$this->quote($folder));
        } catch (RuntimeException) {
            $this->command("UID COPY {$uid} ".$this->quote($folder));
            $this->command("UID STORE {$uid} +FLAGS.SILENT (\\Deleted)");
            $this->command('EXPUNGE');
        }
    }

    public function logout(): void
    {
        if ($this->socket) {
            try {
                $this->command('LOGOUT');
            } catch (RuntimeException) {
            }
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // ------------------------------------------------------------------

    private function quote(string $s): string
    {
        return '"'.addcslashes($s, '"\\').'"';
    }

    private function send(string $cmd): string
    {
        $tag = 'N'.(++$this->tag);
        fwrite($this->socket, $tag.' '.$cmd."\r\n");

        return $tag;
    }

    /** @return list<string> */
    private function command(string $cmd): array
    {
        $tag = $this->send($cmd);
        $lines = [];
        while (true) {
            $line = $this->line();
            if (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $this->read((int) $m[1]);

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                $this->assertOk($line);

                return $lines;
            }
            $lines[] = rtrim($line, "\r\n");
        }
    }

    private function assertOk(string $line): void
    {
        if (! preg_match('/^\S+ OK/i', $line)) {
            throw new RuntimeException('IMAP: '.trim(preg_replace('/LOGIN .*/', 'LOGIN ***', $line)));
        }
    }

    private function line(): string
    {
        $line = fgets($this->socket);
        if ($line === false) {
            throw new RuntimeException('IMAP: połączenie przerwane');
        }

        return $line;
    }

    private function read(int $bytes): string
    {
        $data = '';
        while (strlen($data) < $bytes) {
            $chunk = fread($this->socket, min(8192, $bytes - strlen($data)));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('IMAP: połączenie przerwane');
            }
            $data .= $chunk;
        }

        return $data;
    }
}
