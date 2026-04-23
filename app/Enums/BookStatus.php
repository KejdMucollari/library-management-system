<?php

namespace App\Enums;

enum BookStatus: string
{
    case PlanToRead = 'plan_to_read';
    case Reading = 'reading';
    case Completed = 'completed';
    case Paused = 'paused';

    public static function values(): array
    {
        return array_map(static fn (self $s) => $s->value, self::cases());
    }
}

