<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\OrderLine;

final readonly class OrderStatusLine
{
    private function __construct(
        public string $title,
        public ?string $variant,
        public int $quantity,
        public int $unitPrice,
        public int $lineTotal,
    ) {
    }

    public static function of(OrderLine $line): self
    {
        return new self($line->title(), $line->variant(), $line->quantity(), $line->unitPrice(), $line->total());
    }
}
