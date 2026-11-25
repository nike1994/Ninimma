<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoContent extends Model
{
    protected $fillable = ['site_id', 'seo_phrase_id', 'ticket_id', 'working_title', 'due_on'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function phrase(): BelongsTo { return $this->belongsTo(SeoPhrase::class, 'seo_phrase_id'); }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
}
