<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportPackage extends Model
{
    protected $fillable = ['name', 'kind', 'client_id', 'size', 'ticket_ids', 'created_by'];

    protected function casts(): array
    {
        return ['ticket_ids' => 'array'];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }

    public function path(): string
    {
        return 'exports/'.$this->name;
    }
}
