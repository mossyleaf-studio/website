<?php

declare(strict_types=1);

namespace App\Application\Sales\EditShippingZone;

use App\Domain\Sales\Zone;

final readonly class EditShippingZone
{
    public function __construct(
        public Zone $zone,
        public int $price,
        public ?int $freeFrom,
        public bool $enabled,
    ) {
    }
}
