<?php

namespace App\Enums;

enum Theme: string
{
    case Coastal = 'coastal';
    case Sunset = 'sunset';
    case Forest = 'forest';
    case Midnight = 'midnight';
    case Sandstone = 'sandstone';

    public static function default(): self
    {
        return self::Coastal;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
