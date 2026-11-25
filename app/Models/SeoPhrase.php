<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoPhrase extends Model
{
    protected $fillable = ['site_id', 'phrase', 'target_url', 'priority'];

    protected $attributes = ['priority' => 2];

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function positions(): HasMany { return $this->hasMany(SeoPosition::class)->orderBy('measured_on'); }

    /** [ostatnia pozycja, zmiana względem poprzedniego pomiaru (dodatnia = awans)] */
    public function latestWithChange(): array
    {
        $p = $this->relationLoaded('positions') ? $this->positions : $this->positions()->get();
        $last = $p->last();
        $prev = $p->count() > 1 ? $p[$p->count() - 2] : null;
        $change = $last && $prev && $last->position && $prev->position ? $prev->position - $last->position : null;

        return [$last, $change];
    }
}
