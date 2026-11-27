<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\FileUsage;
use App\Services\BoardSnapshot;
use Illuminate\Http\Request;
use RuntimeException;

/** „Na tablicę” (agencja): wybór tablicy klienta i wysłanie grafiki / stop-klatek z filmu. */
class BoardSnapshotController extends Controller
{
    /** Tablice otwartych zleceń klienta, do którego należy plik. */
    public function targets(FileUsage $usage)
    {
        $client = BoardSnapshot::clientFor($usage);
        abort_unless($client && $usage->file && ($usage->file->isImage() || $usage->file->isVideo()), 422);
        $boards = Board::with('ticket')->whereHas('ticket', fn ($q) => $q->where('client_id', $client->id)->open()->where('archive_state', 'active'))
            ->latest('updated_at')->limit(30)->get();

        return response()->json([
            'client' => $client->name, 'title' => BoardSnapshot::titleFor($usage),
            'kind' => $usage->file->isVideo() ? 'video' : 'image', 'src' => route('files.show', [$usage, $usage->name]),
            'new_title' => __('board.snap_ticket', ['title' => \Illuminate\Support\Str::limit(BoardSnapshot::titleFor($usage), 120)]),
            'boards' => $boards->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'ticket' => '#'.$b->ticket_id.' '.$b->ticket->title])->values(),
        ]);
    }

    public function store(Request $request, FileUsage $usage, BoardSnapshot $snap)
    {
        $data = $request->validate([
            'board_id' => ['nullable', 'integer', 'exists:boards,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:3000'],
            'ask' => ['nullable', 'boolean'],
            'frames' => ['array', 'max:'.BoardSnapshot::MAX_FRAMES],
            'frames.*.data' => ['required', 'string', 'max:14000000'],
            'frames.*.time' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $board = $snap->send($usage, $request->user(), $data['frames'] ?? [], isset($data['board_id']) ? Board::findOrFail($data['board_id']) : null,
                $data['note'] ?? null, $request->boolean('ask'), $data['title'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['url' => route('boards.show', $board), 'message' => __('board.snap_done')]);
    }
}
