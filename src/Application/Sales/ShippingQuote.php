<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ShippingZone;

final readonly class ShippingQuote
{
    private function __construct(
        public string $zone,
        public int $price,
        public ?int $freeFrom,
        public bool $free,
    ) {
    }

    public static function of(ShippingZone $zone, int $goods, int $units): self
    {
        $free = $units > 0 && $zone->isFreeFor($goods);

        return new self($zone->zone()->value, $free || 0 === $units ? 0 : $zone->price(), $zone->freeFrom(), $free);
    }
}
