<?php

namespace App\Services;

use App\Models\FileUsage;
use App\Models\StoredFile;
use App\Models\SystemLog;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Magazyn plików: każdy plik zapisany raz (SHA-256), poza katalogiem publicznym,
 * wysyłany w kawałkach, wydawany przez PHP po sprawdzeniu uprawnień.
 */
class FileStore
{
    public function disk()
    {
        return Storage::disk('local');
    }

    public function maxBytesFor(User $user): ?int
    {
        return $user->isAdmin() ? null : config('agency.client_max_file_mb') * 1024 * 1024;
    }

    public function startUpload(User $user, string $name, ?string $mime, int $size): UploadSession
    {
        $max = $this->maxBytesFor($user);
        if ($max !== null && $size > $max) {
            throw new RuntimeException(__('files.too_large', ['mb' => config('agency.client_max_file_mb')]));
        }
        $chunk = config('agency.chunk_size_kb') * 1024;

        return UploadSession::create([
            'user_id' => $user->id,
            'name' => mb_substr(basename($name), 0, 200),
            'mime' => $mime,
            'size' => $size,
            'total_chunks' => max(1, (int) ceil($size / $chunk)),
        ]);
    }

    public function storeChunk(UploadSession $session, int $index, string $contents): void
    {
        if ($index < 0 || $index >= $session->total_chunks) {
            throw new RuntimeException('Invalid chunk index');
        }
        $this->disk()->put($session->chunkDir().'/'.str_pad((string) $index, 6, '0', STR_PAD_LEFT), $contents);
        $session->received_chunks = count($this->disk()->files($session->chunkDir()));
        $session->save();
    }

    /** Skleja kawałki, liczy sumę kontrolną i zwraca rekord pliku (istniejący lub nowy). */
    public function finishUpload(UploadSession $session): StoredFile
    {
        $chunks = $this->disk()->files($session->chunkDir());
        sort($chunks);
        if (count($chunks) !== $session->total_chunks) {
            throw new RuntimeException('Upload incomplete');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        $out = fopen($tmp, 'wb');
        foreach ($chunks as $chunk) {
            fwrite($out, $this->disk()->get($chunk));
        }
        fclose($out);

        $size = filesize($tmp);
        $max = $this->maxBytesFor(User::findOrFail($session->user_id));
        if ($size !== $session->size || ($max !== null && $size > $max)) {
            @unlink($tmp);
            $this->disk()->deleteDirectory($session->chunkDir());
            SystemLog::record('upload_error', 'Size mismatch or limit exceeded', ['session' => $session->id], 'warning');
            throw new RuntimeException('Upload size mismatch');
        }

        $file = $this->storeLocalFile($tmp, $session->mime);
        @unlink($tmp);
        $this->disk()->deleteDirectory($session->chunkDir());

        return $file;
    }

    public function storeLocalFile(string $path, ?string $mime = null): StoredFile
    {
        $sha = hash_file('sha256', $path);
        if ($existing = StoredFile::where('sha256', $sha)->first()) {
            return $existing;
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        // Ufamy wykrytemu typowi; typ zgłoszony przez przeglądarkę tylko gdy wykrycie ogólne.
        $mime = $detected === 'application/octet-stream' && $mime ? $mime : $detected;
        $relative = 'store/'.substr($sha, 0, 2).'/'.substr($sha, 2, 2).'/'.$sha;
        $stream = fopen($path, 'rb');
        $this->disk()->writeStream($relative, $stream);
        is_resource($stream) && fclose($stream);

        return StoredFile::create(['sha256' => $sha, 'size' => filesize($path), 'mime' => $mime, 'path' => $relative]);
    }

    /** Podpina zakończone wysyłki (identyfikatory użyć bez rekordu) do rekordu. */
    public function attachUploads(array $usageIds, User $user, Model $usable): void
    {
        if (! $usageIds) {
            return;
        }
        FileUsage::whereIn('id', $usageIds)
            ->where('uploaded_by', $user->id)
            ->whereNull('usable_id')
            ->update(['usable_type' => $usable->getMorphClass(), 'usable_id' => $usable->getKey()]);
    }

    public function absolutePath(StoredFile $file): string
    {
        return $this->disk()->path($file->path);
    }
}
