<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dostęp w kartotece klienta — login, hasło i notatki szyfrowane kluczem aplikacji (APP_KEY). */
class ClientCredential extends Model
{
    protected $fillable = ['client_id', 'site_id', 'label', 'url', 'username', 'secret', 'notes'];

    protected $hidden = ['username', 'secret', 'notes'];

    protected function casts(): array
    {
        return ['username' => 'encrypted', 'secret' => 'encrypted', 'notes' => 'encrypted'];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
}
