<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fizyczny plik w magazynie; zapisany raz, rozpoznawany po sumie SHA-256. */
class StoredFile extends Model
{
    protected $table = 'files';

    protected $fillable = ['sha256', 'size', 'mime', 'path', 'location', 'offline_package'];

    public function usages(): HasMany
    {
        return $this->hasMany(FileUsage::class, 'file_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }
}
