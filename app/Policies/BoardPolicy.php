<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return $user->isAdmin() || ($user->client_id !== null && $user->client_id === $board->ticket->client_id);
    }

    public function edit(User $user, Board $board): bool
    {
        return $this->view($user, $board) && $board->ticket->archive_state === 'active';
    }
}
