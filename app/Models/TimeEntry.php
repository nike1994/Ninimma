<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'spent_on', 'minutes', 'note'];

    protected function casts(): array
    {
        return ['spent_on' => 'date'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 90 → "1,5 h", 120 → "2 h" (kafelki i podsumowania) */
    public static function hours(int $minutes): string
    {
        $h = round($minutes / 60, 1);

        return rtrim(rtrim(number_format($h, 1, ',', ' '), '0'), ',').' h';
    }

    /** 90 → "1:30" */
    public static function format(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
