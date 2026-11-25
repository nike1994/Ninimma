<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Treść zapisana raz — publikowana osobno na każdej platformie. */
class Content extends Model
{
    public const TYPES = ['post', 'reel', 'story', 'video', 'article'];

    public const FORMATS = ['image', 'carousel', 'video', 'text'];

    public const STAGES = ['idea', 'preparing', 'ready'];

    protected $fillable = ['client_id', 'campaign_id', 'title', 'description', 'hashtags', 'type', 'format', 'stage', 'created_by'];

    protected $attributes = ['type' => 'post', 'format' => 'image', 'stage' => 'idea'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(FileUsage::class, 'usable');
    }

    /** Pierwszy obraz jako miniatura. */
    public function thumbnail(): ?FileUsage
    {
        $files = $this->relationLoaded('files') ? $this->files : $this->files()->with('file')->get();

        return $files->first(fn (FileUsage $f) => $f->file?->isImage());
    }
}
