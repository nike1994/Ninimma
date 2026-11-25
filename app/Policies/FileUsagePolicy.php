<?php

namespace App\Policies;

use App\Models\FileUsage;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

class FileUsagePolicy
{
    public function view(User $user, FileUsage $usage): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $usable = $usage->usable;
        if ($usable === null) {
            // Wysłany, jeszcze niepodpięty plik widzi tylko autor.
            return $usage->uploaded_by === $user->id;
        }
        if ($usable instanceof TicketMessage) {
            return ! $usable->is_internal && $usable->ticket->client_id === $user->client_id;
        }
        if ($usable instanceof \App\Models\Content) {
            // Klient widzi pliki treści, które mają udostępnioną mu publikację.
            return $usable->client_id === $user->client_id
                && $usable->publications()->where('visible_to_client', true)->exists();
        }
        if ($usable instanceof Ticket) {
            return $usable->client_id === $user->client_id;
        }

        return false; // kolejne typy (tablice, materiały) dochodzą w następnych etapach
    }
}
