<?php

namespace App\Enums;

enum Platform: string
{
    case Ps3 = 'ps3';
    case Ps4 = 'ps4';
    case Ps5 = 'ps5';
    case XboxOne = 'xbox_one';
    case XboxSeries = 'xbox_series';
    case Switch = 'switch';
    case Pc = 'pc';
    case Ds3 = '3ds';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ps3 => 'PS3',
            self::Ps4 => 'PS4',
            self::Ps5 => 'PS5',
            self::XboxOne => 'Xbox One',
            self::XboxSeries => 'Xbox Series',
            self::Switch => 'Nintendo Switch',
            self::Pc => 'PC',
            self::Ds3 => 'Nintendo 3DS',
            self::Other => 'Other',
        };
    }
}
