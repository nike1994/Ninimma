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
        'license_channels', 'requires_takedown', 'kind'];

    protected $attributes = ['is_received' => false, 'requires_takedown' => false, 'kind' => 'video'];

    protected function casts(): array
    {
        return ['is_received' => 'boolean', 'license_from' => 'date', 'license_until' => 'date', 'license_channels' => 'array', 'requires_takedown' => 'boolean'];
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

    public function licenseExpired(): bool
    {
        return $this->license_until !== null && $this->license_until->isBefore(today());
    }

    public function licenseExpiringSoon(int $days = 14): bool
    {
        return $this->license_until !== null && ! $this->licenseExpired() && $this->license_until->lte(today()->addDays($days));
    }
}
