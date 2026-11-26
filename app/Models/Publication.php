<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Publication extends Model
{
    /** Pomiary statystyk po 1, 7 i 30 dniach od publikacji. */
    public const CHECK_DAYS = [1, 7, 30];

    protected $fillable = [
        'content_id', 'social_account_id', 'platform', 'scheduled_at', 'published_at', 'status', 'url', 'caption',
        'promoted', 'promo_budget', 'promo_from', 'promo_to', 'visible_to_client', 'auto_publish',
    ];

    protected $attributes = ['status' => 'planned', 'checks_done' => 0, 'promoted' => false, 'visible_to_client' => false, 'auto_publish' => false, 'api_attempts' => 0];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'promo_from' => 'date',
            'promo_to' => 'date',
            'promoted' => 'boolean',
            'visible_to_client' => 'boolean',
            'auto_publish' => 'boolean',
            'api_next_attempt_at' => 'datetime',
            'promo_budget' => 'decimal:2',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function ad(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdCampaign::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(Measurement::class)->latestOfMany('measured_on');
    }

    /** Dane do szybkiego podglądu w kalendarzu (po najechaniu). */
    public function peek(): array
    {
        $thumb = $this->content->thumbnail();
        $text = $this->caption ?: (string) $this->content->description;

        return [
            'title' => $this->content->title,
            'account' => $this->account?->name.' · '.$this->platform->label(),
            'when' => $this->scheduled_at->translatedFormat('D, j M · H:i'),
            'status' => __('social.pub_'.$this->status),
            'state' => $this->status,
            'client' => $this->client_status ? __('social.client_'.$this->client_status) : null,
            'text' => \Illuminate\Support\Str::limit($text, 180),
            'tags' => \Illuminate\Support\Str::limit((string) $this->content->hashtags, 80),
            'img' => $thumb ? route('files.show', [$thumb, $thumb->name]) : null,
            'video' => $this->content->format === 'video',
            'auto' => $this->auto_publish && $this->status === 'planned',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Termin kolejnego pomiaru albo null, gdy wszystkie zrobione. */
    public function nextCheckAt(): ?Carbon
    {
        if (! $this->isPublished() || ! $this->published_at || $this->checks_done >= count(self::CHECK_DAYS)) {
            return null;
        }

        return $this->published_at->copy()->addDays(self::CHECK_DAYS[$this->checks_done])->startOfDay();
    }

    public function scopeDueForCheck(Builder $q): Builder
    {
        // Wstępny filtr w SQL; dokładny termin liczony w PHP (nextCheckAt).
        return $q->where('status', 'published')->whereNotNull('published_at')
            ->where('checks_done', '<', count(self::CHECK_DAYS))
            ->where('published_at', '<=', now()->subDay()->endOfDay());
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->isAdmin() ? $q : $q->where('visible_to_client', true)
            ->whereHas('content', fn ($c) => $c->where('client_id', $user->client_id));
    }

    public function engagement(): int
    {
        $m = $this->latestMeasurement;

        return $m ? $m->reactions + $m->comments + $m->shares : 0;
    }
}
