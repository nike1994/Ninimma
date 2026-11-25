<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Strona klienta: terminy, cykliczne przeglądy i plan SEO; sama praca odbywa się w zleceniach. */
class Site extends Model
{
    public const PLATFORMS = ['wordpress', 'magento', 'woocommerce', 'shopify', 'other'];

    protected $fillable = ['client_id', 'domain', 'platform', 'hosting', 'care_hours', 'checklist_id', 'review_recurrence', 'next_review_on', 'notes', 'is_active'];

    protected $attributes = ['platform' => 'wordpress', 'is_active' => true];

    protected function casts(): array
    {
        return ['care_hours' => 'decimal:2', 'next_review_on' => 'date', 'is_active' => 'boolean'];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function checklist(): BelongsTo { return $this->belongsTo(Checklist::class); }
    public function deadlines(): HasMany { return $this->hasMany(SiteDeadline::class)->orderBy('due_on'); }
    public function logs(): HasMany { return $this->hasMany(SiteLog::class)->latest('logged_on')->latest('id'); }
    public function reviews(): HasMany { return $this->hasMany(Review::class)->latest('due_on'); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
    public function phrases(): HasMany { return $this->hasMany(SeoPhrase::class)->orderBy('priority')->orderBy('phrase'); }
    public function seoContents(): HasMany { return $this->hasMany(SeoContent::class)->orderBy('due_on'); }
    public function seoTasks(): HasMany { return $this->hasMany(SeoTask::class)->orderByRaw("case status when 'doing' then 0 when 'todo' then 1 else 2 end")->orderBy('priority'); }
    public function backlinks(): HasMany { return $this->hasMany(Backlink::class)->latest('placed_on'); }

    /** Zużycie pakietu godzin w miesiącu (z wpisów czasu zleceń tej strony). */
    public function minutesUsed(Carbon $month): int
    {
        return (int) TimeEntry::whereIn('ticket_id', $this->tickets()->select('id'))
            ->whereDate('spent_on', '>=', $month->copy()->startOfMonth())->whereDate('spent_on', '<=', $month->copy()->endOfMonth())->sum('minutes');
    }

    public function nextDeadline(): ?SiteDeadline
    {
        $d = $this->relationLoaded('deadlines') ? $this->deadlines : $this->deadlines()->get();

        return $d->whereNull('renewed_at')->sortBy('due_on')->first();
    }
}
