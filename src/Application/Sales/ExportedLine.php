<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\OrderLine;

final readonly class ExportedLine
{
    private function __construct(
        public string $sku,
        public string $name,
        public ?string $variant,
        public int $unitPrice,
        public int $quantity,
    ) {
    }

    public static function of(OrderLine $line): self
    {
        return new self($line->sku(), $line->name(), $line->variant(), $line->unitPrice(), $line->quantity());
    }
}
