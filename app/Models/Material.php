<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ustalony materiał (osobna pozycja). Otrzymany plik trafia do biblioteki, po publikacji łączy się z wpisem w planie. */
class Material extends Model
{
    protected $fillable = ['collaboration_id', 'description', 'is_received', 'file_usage_id', 'publication_id', 'license_until'];

    protected $attributes = ['is_received' => false];

    protected function casts(): array
    {
        return ['is_received' => 'boolean', 'license_until' => 'date'];
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
