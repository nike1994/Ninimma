<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dziennik zmian strony: aktualizacje, wdrożenia, awarie, przywrócenia kopii, przeglądy. */
class SiteLog extends Model
{
    public const KINDS = ['update', 'deploy', 'incident', 'restore', 'review', 'other'];

    protected $fillable = ['site_id', 'ticket_id', 'user_id', 'logged_on', 'kind', 'description'];

    protected function casts(): array
    {
        return ['logged_on' => 'date'];
    }

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
