<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDeadline extends Model
{
    public const KINDS = ['domain', 'ssl', 'hosting', 'license'];

    protected $fillable = ['site_id', 'kind', 'name', 'due_on', 'cost', 'renewed_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'renewed_at' => 'datetime', 'cost' => 'decimal:2'];
    }

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->due_on, false);
    }
}
