<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\OrderLine;

final readonly class OrderLineView
{
    private function __construct(
        public string $sku,
        public string $name,
        public string $title,
        public ?string $variant,
        public int $unitPrice,
        public int $quantity,
        public int $lineTotal,
    ) {
    }

    public static function of(OrderLine $line): self
    {
        return new self($line->sku(), $line->name(), $line->title(), $line->variant(), $line->unitPrice(), $line->quantity(), $line->total());
    }
}
