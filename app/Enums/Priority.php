<?php

namespace App\Enums;

enum Priority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return __('priority.'.$this->value);
    }

    public function isElevated(): bool
    {
        return $this !== self::Normal;
    }
}
