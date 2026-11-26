<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'platform', 'name', 'handle', 'url', 'is_active'];

    /** Tokeny nigdy nie trafiają do widoków ani JSON-a. */
    protected $hidden = ['access_token', 'refresh_token'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['platform' => Platform::class, 'is_active' => 'boolean', 'access_token' => 'encrypted', 'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime', 'connected_at' => 'datetime', 'api_meta' => 'array'];
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(FileUsage::class, 'avatar_usage_id');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? route('files.show', [$this->avatar, $this->avatar->name]) : null;
    }

    /**
     * Zapisuje zdjęcie profilu z pliku lub z bajtów (pobranych z platformy): kwadrat 320 px, JPEG.
     * Obraz jest dekodowany i zapisany na nowo — nic poza pikselami nie zostaje.
     */
    public function storeAvatar(string $bytes, ?int $userId = null): bool
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return false;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $side = min($w, $h);
        $im = imagecreatetruecolor(320, 320);
        imagecopyresampled($im, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 320, 320, $side, $side);
        $tmp = tempnam(sys_get_temp_dir(), 'av');
        imagejpeg($im, $tmp, 86);
        imagedestroy($src);
        imagedestroy($im);
        $file = app(\App\Services\FileStore::class)->storeLocalFile($tmp, 'image/jpeg');
        @unlink($tmp);
        $old = $this->avatar;
        $usage = FileUsage::create(['file_id' => $file->id, 'name' => 'profil-'.$this->id.'.jpg', 'uploaded_by' => $userId,
            'usable_type' => 'social_account', 'usable_id' => $this->id]);
        $this->forceFill(['avatar_usage_id' => $usage->id])->save();
        $old?->delete();

        return true;
    }

    /** Konto połączone z API platformy (i integracja włączona). */
    public function isConnected(): bool
    {
        return $this->connected_at !== null && $this->external_id && $this->access_token
            && \App\Services\Social\Integrations::enabledFor($this->platform);
    }

    public function disconnect(?string $error = null): void
    {
        $this->forceFill(['external_id' => null, 'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null,
            'connected_at' => null, 'api_meta' => null, 'api_error' => $error])->save();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
