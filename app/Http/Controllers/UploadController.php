<?php

namespace App\Http\Controllers;

use App\Models\FileUsage;
use App\Models\SystemLog;
use App\Models\UploadSession;
use App\Services\FileStore;
use Illuminate\Http\Request;
use RuntimeException;

class UploadController extends Controller
{
    public function __construct(private FileStore $store) {}

    public function start(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:128'],
        ]);
        try {
            $session = $this->store->startUpload($request->user(), $data['name'], $data['mime'] ?? null, $data['size']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $session->id,
            'chunk_size' => config('agency.chunk_size_kb') * 1024,
            'total_chunks' => $session->total_chunks,
        ]);
    }

    public function chunk(Request $request, UploadSession $upload)
    {
        abort_unless($upload->user_id === $request->user()->id, 403);
        $request->validate(['index' => ['required', 'integer', 'min:0'], 'chunk' => ['required', 'file']]);
        try {
            $this->store->storeChunk($upload, $request->integer('index'), $request->file('chunk')->get());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['received' => $upload->received_chunks]);
    }

    public function finish(Request $request, UploadSession $upload)
    {
        abort_unless($upload->user_id === $request->user()->id, 403);
        try {
            $file = $this->store->finishUpload($upload);
        } catch (RuntimeException $e) {
            SystemLog::record('upload_error', $e->getMessage(), ['user' => $request->user()->id], 'warning');

            return response()->json(['message' => $e->getMessage()], 422);
        }
        $usage = FileUsage::create(['file_id' => $file->id, 'name' => $upload->name, 'uploaded_by' => $request->user()->id]);
        $upload->delete();

        return response()->json([
            'usage_id' => $usage->id,
            'name' => $usage->name,
            'url' => route('files.show', [$usage, $usage->name]),
            'mime' => $file->mime,
        ]);
    }
}
