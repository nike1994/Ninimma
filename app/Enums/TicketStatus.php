<?php

namespace App\Enums;

enum TicketStatus: string
{
    case WithAgency = 'with_agency';
    case WaitingClient = 'waiting_client';
    case NeedsDecision = 'needs_decision';
    case Blocked = 'blocked';
    case Done = 'done';

    /** Po czyjej stronie jest piłka. */
    public function ball(): ?string
    {
        return match ($this) {
            self::WithAgency, self::Blocked => 'agency',
            self::WaitingClient, self::NeedsDecision => 'client',
            self::Done => null,
        };
    }

    public function waitsForClient(): bool
    {
        return $this->ball() === 'client';
    }

    public function label(): string
    {
        return __('status.'.$this->value);
    }

    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn ($s) => $s !== self::Done));
    }
}
