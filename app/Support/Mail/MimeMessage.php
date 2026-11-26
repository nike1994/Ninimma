<?php

namespace App\Support\Mail;

/**
 * Prosty parser wiadomości e-mail (RFC 5322 / MIME) bez rozszerzeń PHP:
 * nagłówki (z dekodowaniem =?UTF-8?…?=), części multipart, base64 / quoted-printable, zestawy znaków, załączniki.
 */
class MimeMessage
{
    /** @var array<string, list<string>> nagłówki (małe litery) */
    public array $headers = [];

    public string $text = '';

    public string $html = '';

    /** @var list<array{name: string, mime: string, content: string}> */
    public array $attachments = [];

    public static function parse(string $raw): self
    {
        $m = new self;
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        [$head, $body] = self::split($raw);
        $m->headers = self::headers($head);
        $m->walk($m->headers, $body, 0);

        return $m;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** @return list<string> */
    public function headerAll(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    /** Adres nadawcy (małe litery) i nazwa. */
    public function from(): array
    {
        return self::address((string) $this->header('from'));
    }

    /** Wszystkie adresy z pól To / Cc / Delivered-To / X-Original-To. */
    public function recipients(): array
    {
        $all = [];
        foreach (['to', 'cc', 'delivered-to', 'x-original-to', 'envelope-to'] as $h) {
            foreach ($this->headerAll($h) as $v) {
                preg_match_all('/[A-Za-z0-9._%+\-=]+@[A-Za-z0-9.\-]+/', $v, $mm);
                array_push($all, ...array_map('strtolower', $mm[0]));
            }
        }

        return array_values(array_unique($all));
    }

    public function subject(): string
    {
        return trim((string) $this->header('subject'));
    }

    /** Treść jako zwykły tekst (z HTML, gdy brak wersji tekstowej). */
    public function plainText(): string
    {
        if (trim($this->text) !== '') {
            return $this->text;
        }
        $html = preg_replace('~<(style|script|head)[^>]*>.*?</\1>~is', '', $this->html);
        $html = preg_replace('~<blockquote[^>]*>~i', "\n> ", (string) $html);
        $html = preg_replace('~<br\s*/?>|</p>|</div>|</li>|</tr>|</h\d>~i', "\n", (string) $html);

        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // ------------------------------------------------------------------

    private static function split(string $raw): array
    {
        $pos = strpos($raw, "\n\n");

        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    private static function headers(string $head): array
    {
        $out = [];
        $head = preg_replace("/\n[ \t]+/", ' ', $head); // zawijanie linii
        foreach (explode("\n", (string) $head) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $out[strtolower(trim($k))][] = self::decodeHeader(trim($v));
        }

        return $out;
    }

    public static function decodeHeader(string $v): string
    {
        if (! str_contains($v, '=?')) {
            return $v;
        }
        // Sąsiednie zakodowane słowa łączą się bez spacji.
        $v = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $v);

        return (string) preg_replace_callback('/=\?([^?]+)\?([QqBb])\?([^?]*)\?=/', function ($m) {
            $data = strtoupper($m[2]) === 'B' ? base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));

            return self::toUtf8((string) $data, $m[1]);
        }, (string) $v);
    }

    /** Parametr nagłówka (np. boundary, charset, filename — także filename*=UTF-8''...). */
    private static function param(string $header, string $name): ?string
    {
        if (preg_match('/'.$name.'\*0?\*?=([^;]+)/i', $header, $m)) {
            $parts = [];
            preg_match_all('/'.$name.'\*(\d+)\*?=([^;]+)/i', $header, $all, PREG_SET_ORDER);
            $val = $all ? implode('', array_map(fn ($a) => trim($a[2], " \""), $all)) : trim($m[1], " \"");
            if (preg_match("/^([^']*)'[^']*'(.*)$/", $val, $e)) {
                return self::toUtf8(rawurldecode($e[2]), $e[1] ?: 'UTF-8');
            }

            return rawurldecode($val);
        }
        if (preg_match('/'.$name.'\s*=\s*"([^"]*)"/i', $header, $m) || preg_match('/'.$name.'\s*=\s*([^;\s]+)/i', $header, $m)) {
            return self::decodeHeader($m[1]);
        }

        return null;
    }

    public static function address(string $v): array
    {
        if (preg_match('/<([^>]+)>/', $v, $m)) {
            $email = $m[1];
            $name = trim(str_replace($m[0], '', $v), " \"'");
        } else {
            $email = trim($v);
            $name = '';
        }

        return ['email' => strtolower(trim($email)), 'name' => $name];
    }

    private static function toUtf8(string $s, ?string $charset): string
    {
        $charset = strtoupper(trim((string) $charset, " \"'")) ?: 'UTF-8';
        if ($charset === 'UTF-8' || $charset === 'US-ASCII') {
            return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-2');
        }
        $out = @iconv($charset, 'UTF-8//TRANSLIT', $s);

        return $out !== false ? $out : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-2');
    }

    private function walk(array $headers, string $body, int $depth): void
    {
        if ($depth > 8) {
            return;
        }
        $type = $headers['content-type'][0] ?? 'text/plain';
        $mime = strtolower(trim(explode(';', $type)[0]));
        $disposition = $headers['content-disposition'][0] ?? '';
        $encoding = strtolower(trim($headers['content-transfer-encoding'][0] ?? '7bit'));

        if (str_starts_with($mime, 'multipart/')) {
            $boundary = self::param($type, 'boundary');
            if (! $boundary) {
                return;
            }
            $parts = preg_split('/^--'.preg_quote($boundary, '/').'(?:--)?[ \t]*$/m', $body);
            array_shift($parts); // preambuła
            foreach ($parts as $part) {
                $part = ltrim($part, "\n");
                if (trim($part) === '') {
                    continue;
                }
                [$h, $b] = self::split($part);
                $this->walk(self::headers($h), $b, $depth + 1);
            }

            return;
        }
        if ($mime === 'message/rfc822' && ! str_contains(strtolower($disposition), 'attachment')) {
            return; // przekazana w całości wiadomość — pomijamy
        }

        $data = match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $filename = self::param($disposition, 'filename') ?? self::param($type, 'name');
        $isAttachment = $filename !== null || str_starts_with(strtolower($disposition), 'attachment')
            || ! in_array($mime, ['text/plain', 'text/html'], true);

        if ($isAttachment) {
            if ($data !== '') {
                $this->attachments[] = ['name' => $filename ?: 'zalacznik', 'mime' => $mime, 'content' => $data];
            }

            return;
        }
        $text = self::toUtf8($data, self::param($type, 'charset'));
        if ($mime === 'text/html') {
            $this->html .= $text;
        } else {
            $this->text .= ($this->text !== '' ? "\n" : '').$text;
        }
    }
}
