<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || ($user->client_id !== null && $user->client_id === $ticket->client_id);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && ($user->isAdmin() || $ticket->archive_state === 'active');
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}
