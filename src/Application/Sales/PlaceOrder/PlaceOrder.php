<?php

declare(strict_types=1);

namespace App\Application\Sales\PlaceOrder;

use App\Application\Sales\CartLine;
use App\Domain\Sales\Delivery;
use App\Domain\Shared\Locale;

final readonly class PlaceOrder
{
    /** @param list<CartLine> $lines */
    public function __construct(
        public array $lines,
        public string $country,
        public Delivery $delivery,
        public Locale $locale,
        public bool $preview,
        public int $expectedTotal,
    ) {
    }
}
