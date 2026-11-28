<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardFile;
use App\Models\BoardVersion;
use App\Models\FileUsage;
use App\Models\Read;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tablice: synchronizacja przez odpytywanie. Przesyłane są tylko zmienione elementy,
 * łączone po id i numerze wersji elementu (wygrywa wyższa wersja).
 */
class BoardService
{
    public function __construct(private TicketService $tickets, private FileStore $files) {}

    public function create(Ticket $ticket, User $by, string $name): Board
    {
        $board = $ticket->boards()->create(['name' => $name, 'updated_by' => $by->id]);
        $this->tickets->event($ticket, $by, 'board_created', ['name' => $name]);

        return $board;
    }

    /** Scala elementy od przeglądarki; zwraca listę id faktycznie przyjętych. */
    public function merge(Board $board, User $by, array $incoming): array
    {
        return DB::transaction(function () use ($board, $by, $incoming) {
            $board = Board::lockForUpdate()->find($board->id);
            $elements = $board->elements ?? [];
            $revisions = $board->revisions ?? [];
            $accepted = [];
            $next = $board->revision + 1;

            foreach ($incoming as $el) {
                if (! is_array($el) || empty($el['id']) || ! isset($el['version'])) {
                    continue;
                }
                $id = (string) $el['id'];
                $current = $elements[$id] ?? null;
                if ($current && (int) $current['version'] >= (int) $el['version']) {
                    continue; // starsza lub ta sama wersja — ignorujemy
                }
                // Strona autora (blokada cudzych elementów po stronie przeglądarki).
                $el['customData'] = ($el['customData'] ?? []) + ['side' => $current['customData']['side'] ?? ($by->isAdmin() ? 'admin' : 'client')];
                $elements[$id] = $el;
                $revisions[$id] = $next;
                $accepted[] = $id;
            }

            if ($accepted) {
                $board->forceFill(['elements' => $elements, 'revisions' => $revisions, 'revision' => $next, 'updated_by' => $by->id])->save();
            }

            return $accepted;
        });
    }

    /** Elementy zmienione po podanym numerze zmiany. */
    public function since(Board $board, int $since): array
    {
        // Rzutowanie „array” dekoduje cały JSON przy każdym odczycie — pobieramy raz, poza pętlą.
        $elements = $board->elements ?? [];
        $out = [];
        foreach ($board->revisions ?? [] as $id => $rev) {
            if ($rev > $since && isset($elements[$id])) {
                $out[] = $elements[$id];
            }
        }

        return $out;
    }

    public function touchPresence(Board $board, User $user): void
    {
        DB::table('board_presence')->upsert(
            [['board_id' => $board->id, 'user_id' => $user->id, 'seen_at' => now()]],
            ['board_id', 'user_id'], ['seen_at'],
        );
    }

    /** Kto ma teraz otwartą tablicę (ostatnie 20 s). */
    public function presence(Board $board, User $except): array
    {
        return DB::table('board_presence')->join('users', 'users.id', '=', 'board_presence.user_id')
            ->where('board_id', $board->id)->where('user_id', '!=', $except->id)
            ->where('seen_at', '>=', now()->subSeconds(20))
            ->pluck('users.name')->all();
    }

    /** Świadomie zapisana wersja ("Zapisz wersję") albo automatyczna po zakończeniu sesji. */
    public function saveVersion(Board $board, User $by, ?string $description, bool $auto = false): ?BoardVersion
    {
        $board->refresh();
        $last = $board->versions()->latest('id')->first();
        if ($auto && ($board->revision === 0 || ($last && $last->revision >= $board->revision))) {
            return null; // nic nowego od ostatniej wersji
        }
        $version = $board->versions()->create([
            'user_id' => $by->id, 'elements' => $board->liveElements(), 'description' => $description ?: null,
            'is_auto' => $auto, 'revision' => $board->revision,
        ]);
        $ticket = $board->ticket;
        $ticket->forceFill(['board_version_at' => now(), 'board_version_by' => $by->id, 'last_activity_at' => now()])->save();
        $this->tickets->event($ticket, $by, 'board_version', array_filter(['name' => $board->name, 'description' => $description]));
        $this->markSeen($board, $by, $version);

        return $version;
    }

    /** Przywrócenie wersji: elementy dostają wyższe numery wersji, żeby wygrać z bieżącym stanem. */
    public function restore(Board $board, BoardVersion $version, User $by): void
    {
        DB::transaction(function () use ($board, $version, $by) {
            $board = Board::lockForUpdate()->find($board->id);
            $current = $board->elements ?? [];
            $snapshot = collect($version->elements)->keyBy('id')->all();
            $incoming = [];
            foreach ($snapshot as $id => $el) {
                $el['version'] = max((int) ($current[$id]['version'] ?? 0), (int) $el['version']) + 1;
                $el['versionNonce'] = random_int(1, PHP_INT_MAX);
                $el['isDeleted'] = false;
                $incoming[] = $el;
            }
            foreach ($current as $id => $el) {
                if (! isset($snapshot[$id]) && empty($el['isDeleted'])) {
                    $el['isDeleted'] = true;
                    $el['version'] = (int) $el['version'] + 1;
                    $incoming[] = $el;
                }
            }
            $this->merge($board, $by, $incoming);
        });
        $this->saveVersion($board, $by, __('ui.restored_version', ['date' => $version->created_at->format('d.m H:i')]));
    }

    public function markSeen(Board $board, User $user, ?BoardVersion $version = null): void
    {
        $version ??= $board->versions()->latest('id')->first();
        Read::updateOrCreate(
            ['user_id' => $user->id, 'readable_type' => 'board', 'readable_id' => $board->id],
            ['last_seen_id' => $version?->id ?? 0, 'seen_at' => now()],
        );
    }

    /** Lista zmian względem wersji ostatnio przejrzanej przez użytkownika. */
    public function changesFor(Board $board, User $user): array
    {
        $read = $board->reads()->where('user_id', $user->id)->first();
        $seen = $read ? BoardVersion::find($read->last_seen_id) : null;
        if (! $seen) {
            return ['added' => [], 'changed' => [], 'removed' => [], 'since' => null];
        }
        $before = collect($seen->elements)->keyBy('id');
        $now = collect($board->liveElements())->keyBy('id');
        $label = fn ($el) => ['id' => $el['id'], 'type' => $el['type'], 'text' => mb_substr((string) ($el['text'] ?? $el['link'] ?? ''), 0, 60)];

        return [
            'added' => $now->diffKeys($before)->map($label)->values()->all(),
            'changed' => $now->intersectByKeys($before)->filter(fn ($el, $id) => $el['version'] !== $before[$id]['version'])->map($label)->values()->all(),
            'removed' => $before->diffKeys($now)->map($label)->values()->all(),
            'since' => $seen->created_at->format('d.m H:i'),
        ];
    }

    /** Podgląd wersji jako SVG (bez skryptów i zdarzeń — odrzucamy podejrzaną treść). */
    public function storePreview(BoardVersion $version, User $by, string $svg): void
    {
        if (! str_starts_with(ltrim($svg), '<svg') || preg_match('/<script|javascript:|\son\w+\s*=|<foreignObject/i', $svg)) {
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($tmp, $svg);
        $file = $this->files->storeLocalFile($tmp, 'image/svg+xml');
        @unlink($tmp);
        $usage = FileUsage::create(['file_id' => $file->id, 'usable_type' => 'board_version', 'usable_id' => $version->id,
            'name' => 'podglad-'.$version->id.'.svg', 'uploaded_by' => $by->id]);
        $version->update(['preview_usage_id' => $usage->id]);
    }

    /** Obraz wklejony na tablicę (data URL) → magazyn plików. */
    public function storeImage(Board $board, User $by, string $excalidrawId, string $dataUrl): BoardFile
    {
        if (! preg_match('#^data:(image/(png|jpeg|gif|webp|svg\+xml));base64,(.+)$#s', $dataUrl, $m)) {
            throw new RuntimeException('Unsupported image');
        }
        $binary = base64_decode($m[3], true);
        if ($binary === false || strlen($binary) > 15 * 1024 * 1024) {
            throw new RuntimeException('Image too large');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'brd');
        file_put_contents($tmp, $binary);
        $file = $this->files->storeLocalFile($tmp, $m[1]);
        @unlink($tmp);
        $usage = FileUsage::create(['file_id' => $file->id, 'usable_type' => 'board', 'usable_id' => $board->id,
            'name' => 'tablica-'.$board->id.'-'.substr($excalidrawId, 0, 8).'.'.explode('+', $m[2])[0], 'uploaded_by' => $by->id]);

        return BoardFile::firstOrCreate(['board_id' => $board->id, 'excalidraw_id' => $excalidrawId], ['file_usage_id' => $usage->id, 'mime' => $m[1]]);
    }
}
