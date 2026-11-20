<?php

declare(strict_types=1);

namespace App\Application\Sales\PlaceOrder;

final readonly class PlacedOrder
{
    public function __construct(
        public string $reference,
        public string $token,
        public string $paymentUrl,
    ) {
    }
}
