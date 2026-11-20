<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ShippingZone;

final readonly class ShippingZoneView
{
    private function __construct(
        public string $code,
        public int $price,
        public ?int $freeFrom,
        public bool $enabled,
    ) {
    }

    public static function of(ShippingZone $zone): self
    {
        return new self($zone->zone()->value, $zone->price(), $zone->freeFrom(), $zone->isEnabled());
    }
}
