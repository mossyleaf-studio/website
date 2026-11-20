<?php

declare(strict_types=1);

namespace App\Domain\Sales;

enum Zone: string
{
    case France = 'france';
    case Eu = 'eu';
    case World = 'world';

    public const array FRANCE = ['FR', 'MC'];
    public const array EUROPEAN_UNION = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    public static function of(Country $country): self
    {
        return match (true) {
            \in_array($country->code, self::FRANCE, true) => self::France,
            \in_array($country->code, self::EUROPEAN_UNION, true) => self::Eu,
            default => self::World,
        };
    }
}
