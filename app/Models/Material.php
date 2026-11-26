<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ustalony materiał (osobna pozycja). Otrzymany plik trafia do biblioteki, po publikacji łączy się z wpisem w planie. */
class Material extends Model
{
    /** Kanały użycia materiału objęte licencją. */
    public const CHANNELS = ['organic', 'paid', 'website', 'email', 'print'];

    public const KINDS = ['video', 'photo', 'story', 'other'];

    protected $fillable = ['collaboration_id', 'description', 'is_received', 'file_usage_id', 'publication_id', 'license_from', 'license_until',
        'license_channels', 'requires_takedown', 'kind', 'client_download'];

    protected $attributes = ['is_received' => false, 'requires_takedown' => false, 'kind' => 'video', 'client_download' => false];

    protected function casts(): array
    {
        return ['is_received' => 'boolean', 'license_from' => 'date', 'license_until' => 'date', 'license_channels' => 'array', 'requires_takedown' => 'boolean', 'client_download' => 'boolean'];
    }

    public function contents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Content::class);
    }

    public function ads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function extensions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LicenseExtension::class)->latest();
    }

    /** Brak listy kanałów = licencja na wszystkie kanały (zgodnie z umową bez ograniczeń). */
    public function allows(string $channel): bool
    {
        return empty($this->license_channels) || in_array($channel, $this->license_channels, true);
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'file_usage_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Publikacje z tym materiałem: posty treści, w których materiał zaznaczono, oraz (starsze) ręczne powiązanie.
     * Jedno źródło dla raportu kampanii, strażnika licencji i karty współpracy.
     */
    public function usedPublicationIds(): \Illuminate\Support\Collection
    {
        $contentIds = $this->relationLoaded('contents') ? $this->contents->pluck('id') : $this->contents()->pluck('contents.id');

        return Publication::whereIn('content_id', $contentIds)->where('status', '!=', 'cancelled')->pluck('id')
            ->when($this->publication_id, fn ($c) => $c->push($this->publication_id))->unique()->values();
    }

    public function usedPublications(): \Illuminate\Support\Collection
    {
        return Publication::with(['content', 'account'])->whereIn('id', $this->usedPublicationIds())->orderBy('scheduled_at')->get();
    }

    /** Może trafić do nowego posta (licencja nie wygasła i obejmuje posty). */
    public function usableForPost(): bool
    {
        return ! $this->licenseExpired() && $this->allows('organic');
    }

    public function licenseExpired(): bool
    {
        return $this->license_until !== null && $this->license_until->isBefore(today());
    }

    public function licenseExpiringSoon(int $days = 14): bool
    {
        return $this->license_until !== null && ! $this->licenseExpired() && $this->license_until->lte(today()->addDays($days));
    }
}
