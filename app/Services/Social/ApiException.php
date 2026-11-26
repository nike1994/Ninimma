<?php

namespace App\Services\Social;

use RuntimeException;

/** Błąd API platformy. $tokenInvalid = połączenie wygasło/odwołane (trzeba połączyć konto ponownie). */
class ApiException extends RuntimeException
{
    public function __construct(string $message, public bool $tokenInvalid = false, public bool $retryable = true)
    {
        parent::__construct($message);
    }
}
