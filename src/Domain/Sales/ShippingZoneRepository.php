<?php

declare(strict_types=1);

namespace App\Domain\Sales;

interface ShippingZoneRepository
{
    public function get(Zone $zone): ShippingZone;

    /** @return list<ShippingZone> */
    public function all(): array;
}
