<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Board;
use App\Models\BoardFile;
use App\Models\Client;
use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * „Na tablicę”: grafika albo stop-klatka z filmu trafia na tablicę zlecenia jako pomniejszona kopia ze znakiem
 * wodnym klienta (oryginał zostaje chroniony). Klient rysuje strzałki i pisze komentarze na tablicy.
 */
class BoardSnapshot
{
    public const MAX_FRAMES = 6;

    public const SIZE = 1600;

    public function __construct(private BoardService $boards, private TicketService $tickets, private FileStore $files) {}

    /** Klient, którego dotyczy plik (materiał kampanii albo treść/post). */
    public static function clientFor(FileUsage $usage): ?Client
    {
        $u = $usage->usable;

        return match (true) {
            $u instanceof Material => $u->collaboration?->campaign?->client,
            $u instanceof Content => $u->client,
            default => null,
        };
    }

    public static function titleFor(FileUsage $usage): string
    {
        $u = $usage->usable;

        return match (true) {
            $u instanceof Material => $u->description ?: $usage->name,
            $u instanceof Content => $u->title,
            default => $usage->name,
        };
    }

    /**
     * @param  array  $frames  [['data' => data URL JPEG/PNG, 'time' => sekundy|null]] — dla filmu; dla grafiki puste (bierzemy plik)
     * @param  Board|null  $board  istniejąca tablica albo null = nowe zlecenie z tablicą
     */
    public function send(FileUsage $usage, User $by, array $frames, ?Board $board, ?string $note, bool $askClient, ?string $newTitle = null): Board
    {
        $client = self::clientFor($usage) ?? throw new RuntimeException(__('board.snap_no_client'));
        $stored = $usage->file;
        $title = self::titleFor($usage);
        $label = mb_strtoupper(__('files.preview_mark')).' · '.$client->name.' · '.today()->format('d.m.Y');

        // Obrazy do wstawienia (już ze znakiem wodnym).
        $images = [];
        if ($stored->isImage()) {
            $jpg = MediaProtection::watermark((string) file_get_contents($this->files->absolutePath($stored)), $label, self::SIZE);
            $images[] = ['jpg' => $jpg, 'caption' => $title];
        } else {
            foreach (array_slice($frames, 0, self::MAX_FRAMES) as $f) {
                if (! preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', (string) ($f['data'] ?? ''), $m)) {
                    continue;
                }
                $bin = base64_decode($m[2], true);
                if ($bin === false || strlen($bin) > 10 * 1024 * 1024) {
                    continue;
                }
                $time = isset($f['time']) && is_numeric($f['time']) ? self::clock((float) $f['time']) : null;
                $images[] = ['jpg' => MediaProtection::watermark($bin, $label, self::SIZE, $time), 'caption' => $title.($time ? ' — '.__('board.snap_frame', ['time' => $time]) : '')];
            }
        }
        $images = array_values(array_filter($images, fn ($i) => $i['jpg']));
        if (! $images) {
            throw new RuntimeException(__('board.snap_nothing'));
        }

        return DB::transaction(function () use ($usage, $by, $images, $board, $note, $askClient, $client, $title, $newTitle) {
            if (! $board) {
                $ticket = $this->tickets->create($by, ['title' => $newTitle ?: __('board.snap_ticket', ['title' => Str::limit($title, 120)]), 'client_id' => $client->id,
                    'type' => 'task', 'campaign_id' => $usage->usable instanceof Material ? $usage->usable->collaboration?->campaign_id : $usage->usable?->campaign_id], null);
                $board = $this->boards->create($ticket, $by, __('board.snap_board'));
            }
            abort_unless($board->ticket->client_id === $client->id, 422);

            // Miejsce: pod dotychczasową zawartością tablicy.
            $live = $board->liveElements();
            $y = $live ? max(array_map(fn ($e) => ($e['y'] ?? 0) + ($e['height'] ?? 0), $live)) + 120 : 0;
            $x = 0;
            // Jeden podpis nad rzędem kadrów (czas klatki jest w rogu obrazu).
            $elements = [$this->text(0, $y, $images[0]['caption'] === $title || count($images) > 1 ? $title : $images[0]['caption'])];
            $y += 40;
            $rowH = 0;
            foreach ($images as $img) {
                $fileId = Str::random(40);
                [$w, $h] = getimagesizefromstring($img['jpg']) ?: [1600, 900];
                $scale = min(1, 800 / max(1, $w), 600 / max(1, $h));
                [$dw, $dh] = [round($w * $scale), round($h * $scale)];
                if ($x > 0 && $x + $dw > 2600) {
                    $x = 0;
                    $y += $rowH + 60;
                    $rowH = 0;
                }
                $this->storeBoardImage($board, $by, $fileId, $img['jpg']);
                $elements[] = $this->image($x, $y, $dw, $dh, $fileId);
                $x += $dw + 60;
                $rowH = max($rowH, $dh);
            }
            $this->boards->merge($board, $by, $elements);
            $this->boards->saveVersion($board, $by, __('board.snap_version', ['title' => Str::limit($title, 80), 'n' => count($images)]));

            $ticket = $board->ticket;
            $body = trim(($note ? $note."\n\n" : '').__('board.snap_message', ['n' => count($images), 'title' => $title, 'board' => $board->name]));
            $this->tickets->postMessage($ticket, $by, $body, false);
            if ($askClient && $ticket->status !== TicketStatus::NeedsDecision) {
                $this->tickets->changeStatus($ticket, $by, TicketStatus::WaitingClient);
            }

            return $board;
        });
    }

    private function storeBoardImage(Board $board, User $by, string $fileId, string $jpg): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'snap');
        file_put_contents($tmp, $jpg);
        $file = $this->files->storeLocalFile($tmp, 'image/jpeg');
        @unlink($tmp);
        $usage = FileUsage::create(['file_id' => $file->id, 'usable_type' => 'board', 'usable_id' => $board->id,
            'name' => 'tablica-'.$board->id.'-'.substr($fileId, 0, 8).'.jpg', 'uploaded_by' => $by->id]);
        BoardFile::create(['board_id' => $board->id, 'excalidraw_id' => $fileId, 'file_usage_id' => $usage->id, 'mime' => 'image/jpeg']);
    }

    /** Wspólne pola elementu Excalidraw. */
    private function base(string $type, float $x, float $y, float $w, float $h): array
    {
        return ['id' => Str::random(20), 'type' => $type, 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'angle' => 0,
            'strokeColor' => '#0b2a4a', 'backgroundColor' => 'transparent', 'fillStyle' => 'solid', 'strokeWidth' => 1, 'strokeStyle' => 'solid',
            'roughness' => 0, 'opacity' => 100, 'groupIds' => [], 'frameId' => null, 'roundness' => null, 'seed' => random_int(1, 2 ** 31 - 1),
            'version' => 1, 'versionNonce' => random_int(1, 2 ** 31 - 1), 'isDeleted' => false, 'boundElements' => null,
            'updated' => (int) (microtime(true) * 1000), 'link' => null, 'locked' => false, 'customData' => ['side' => 'admin', 'snapshot' => true]];
    }

    private function image(float $x, float $y, float $w, float $h, string $fileId): array
    {
        return $this->base('image', $x, $y, $w, $h) + ['status' => 'saved', 'fileId' => $fileId, 'scale' => [1, 1], 'crop' => null];
    }

    private function text(float $x, float $y, string $text): array
    {
        $text = Str::limit($text, 90);

        return $this->base('text', $x, $y, min(800, mb_strlen($text) * 11), 25) + ['text' => $text, 'originalText' => $text, 'fontSize' => 20,
            'fontFamily' => 2, 'textAlign' => 'left', 'verticalAlign' => 'top', 'containerId' => null, 'lineHeight' => 1.25, 'autoResize' => true];
    }

    public static function clock(float $sec): string
    {
        $s = (int) floor($sec);

        return intdiv($s, 60).':'.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
    }
}
