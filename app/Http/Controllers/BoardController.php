<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\BoardVersion;
use App\Models\Ticket;
use App\Services\BoardService;
use App\Services\FileStore;
use Illuminate\Http\Request;
use RuntimeException;

class BoardController extends Controller
{
    public function __construct(private BoardService $boards) {}

    public function store(Request $request, Ticket $ticket)
    {
        $this->authorize('reply', $ticket);
        $board = $this->boards->create($ticket, $request->user(), $request->validate(['name' => ['required', 'string', 'max:120']])['name']);

        return redirect()->route('boards.show', $board);
    }

    public function show(Request $request, Board $board)
    {
        $this->authorize('view', $board);
        $user = $request->user();
        $changes = $this->boards->changesFor($board, $user);
        $this->boards->touchPresence($board, $user);

        return view('boards.show', [
            'board' => $board->load('ticket.client'),
            'versions' => $board->versions()->with(['user', 'preview'])->latest('id')->limit(30)->get(),
            'changes' => $changes,
            'unseen' => $board->hasUnseenVersionFor($user),
            'canEdit' => $user->can('edit', $board),
            'side' => $user->isAdmin() ? 'admin' : 'client',
        ]);
    }

    /** Stan początkowy + zmiany od podanego numeru (odpytywanie). */
    public function poll(Request $request, Board $board)
    {
        $this->authorize('view', $board);
        $this->boards->touchPresence($board, $request->user());
        $board->refresh();

        return response()->json($this->payload($board, $request->integer('since'), $request));
    }

    public function push(Request $request, Board $board)
    {
        $this->authorize('edit', $board);
        $data = $request->validate(['elements' => ['array', 'max:2000'], 'since' => ['integer']]);
        $this->boards->merge($board, $request->user(), $data['elements'] ?? []);
        $this->boards->touchPresence($board, $request->user());
        $board->refresh();

        return response()->json($this->payload($board, (int) ($data['since'] ?? 0), $request));
    }

    private function payload(Board $board, int $since, Request $request): array
    {
        return [
            'revision' => $board->revision,
            'elements' => $since === 0 ? array_values($board->elements ?? []) : $this->boards->since($board, $since),
            'presence' => $this->boards->presence($board, $request->user()),
            'files' => $board->files()->get(['excalidraw_id', 'mime'])->map(fn ($f) => [
                'id' => $f->excalidraw_id, 'mimeType' => $f->mime, 'url' => route('boards.files.show', [$board, $f->excalidraw_id]),
            ])->all(),
        ];
    }

    public function saveVersion(Request $request, Board $board)
    {
        $this->authorize('edit', $board);
        $data = $request->validate(['description' => ['nullable', 'string', 'max:200'], 'preview' => ['nullable', 'string', 'max:3000000']]);
        $version = $this->boards->saveVersion($board, $request->user(), $data['description'] ?? null);
        if ($version && ! empty($data['preview'])) {
            $this->boards->storePreview($version, $request->user(), $data['preview']);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', __('ui.version_saved'));
    }

    /** Wywoływane przez sendBeacon przy zamknięciu tablicy: wersja po zakończeniu sesji edycji. */
    public function sessionEnd(Request $request, Board $board)
    {
        $this->authorize('edit', $board);
        $version = $this->boards->saveVersion($board, $request->user(), null, true);
        if ($version && $request->filled('preview')) {
            $this->boards->storePreview($version, $request->user(), (string) $request->input('preview'));
        }

        return response()->noContent();
    }

    public function version(Request $request, Board $board, BoardVersion $version)
    {
        $this->authorize('view', $board);
        abort_unless($version->board_id === $board->id, 404);

        return view('boards.version', ['board' => $board->load('ticket'), 'version' => $version->load('user'), 'canEdit' => $request->user()->can('edit', $board)]);
    }

    public function restore(Request $request, Board $board, BoardVersion $version)
    {
        $this->authorize('edit', $board);
        abort_unless($version->board_id === $board->id, 404);
        $this->boards->restore($board, $version, $request->user());

        return redirect()->route('boards.show', $board)->with('status', __('ui.version_restored'));
    }

    /** Obraz podglądu wersji (SVG z przeglądarki), jeśli jeszcze go nie ma. */
    public function storePreview(Request $request, Board $board, BoardVersion $version)
    {
        $this->authorize('view', $board);
        abort_unless($version->board_id === $board->id, 404);
        if (! $version->preview_usage_id) {
            $this->boards->storePreview($version, $request->user(), (string) $request->validate(['preview' => ['required', 'string', 'max:3000000']])['preview']);
        }

        return response()->json(['ok' => true]);
    }

    public function seen(Request $request, Board $board)
    {
        $this->authorize('view', $board);
        $this->boards->markSeen($board, $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function storeFile(Request $request, Board $board)
    {
        $this->authorize('edit', $board);
        $data = $request->validate(['id' => ['required', 'string', 'max:100'], 'dataURL' => ['required', 'string']]);
        try {
            $this->boards->storeImage($board, $request->user(), $data['id'], $data['dataURL']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function file(Request $request, Board $board, string $fileId, FileStore $store)
    {
        $this->authorize('view', $board);
        $bf = $board->files()->where('excalidraw_id', $fileId)->firstOrFail();

        return response()->file($store->absolutePath($bf->usage->file), ['Content-Type' => $bf->mime, 'Cache-Control' => 'private, max-age=604800']);
    }
}
