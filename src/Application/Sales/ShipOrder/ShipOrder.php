<?php

declare(strict_types=1);

namespace App\Application\Sales\ShipOrder;

use Symfony\Component\Uid\Ulid;

final readonly class ShipOrder
{
    public function __construct(
        public Ulid $id,
        public ?string $trackingNumber,
    ) {
    }
}
