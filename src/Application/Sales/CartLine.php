<?php

declare(strict_types=1);

namespace App\Application\Sales;

final readonly class CartLine
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {
    }
}
