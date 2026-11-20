<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Domain\Catalogue\ProductVariant;

final readonly class VariantView
{
    private function __construct(
        public string $sku,
        public ?string $name,
        public int $stock,
    ) {
    }

    public static function of(ProductVariant $variant): self
    {
        return new self($variant->sku(), $variant->name(), $variant->stock());
    }
}
