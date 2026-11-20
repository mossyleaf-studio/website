<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\ShippingZone;
use App\Domain\Sales\ShippingZoneRepository;
use App\Domain\Sales\Zone;

final readonly class ShippingZoneQueries
{
    public function __construct(private ShippingZoneRepository $zones)
    {
    }

    /** @return list<ShippingZoneView> */
    public function all(): array
    {
        return array_map(ShippingZoneView::of(...), $this->zones->all());
    }

    /** @return list<Zone> */
    public function enabled(): array
    {
        return array_values(array_map(
            static fn (ShippingZone $zone): Zone => $zone->zone(),
            array_filter($this->zones->all(), static fn (ShippingZone $zone): bool => $zone->isEnabled()),
        ));
    }
}
