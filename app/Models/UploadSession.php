<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'name', 'mime', 'size', 'total_chunks', 'received_chunks'];

    public function chunkDir(): string
    {
        return 'chunks/'.$this->id;
    }
}
