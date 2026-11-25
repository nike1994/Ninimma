<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['site_id', 'ticket_id', 'checklist_id', 'due_on', 'items', 'checked', 'notes', 'completed_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'items' => 'array', 'checked' => 'array', 'completed_at' => 'datetime'];
    }

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }

    public function progress(): array
    {
        $done = count(array_filter($this->checked ?? []));

        return [$done, count($this->items ?? [])];
    }
}
