<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Powiązanie: który rekord (wiadomość, wersja tablicy, materiał...) używa którego pliku. */
class FileUsage extends Model
{
    protected $fillable = ['file_id', 'usable_type', 'usable_id', 'name', 'uploaded_by'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function usable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
