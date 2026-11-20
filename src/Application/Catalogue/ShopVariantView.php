<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Domain\Catalogue\ProductVariant;

final readonly class ShopVariantView
{
    public const int MAX_AVAILABLE = 10;
    public const int LAST_PIECES = 3;

    private function __construct(
        public string $sku,
        public ?string $name,
        public int $available,
        public bool $soldOut,
        public string $state,
    ) {
    }

    public static function of(ProductVariant $variant, bool $amongSeveral, int $held): self
    {
        $available = min(max(0, $variant->stock() - $held), self::MAX_AVAILABLE);

        return new self(
            $variant->sku(),
            $variant->name() ?? ($amongSeveral ? $variant->sku() : null),
            $available,
            0 === $available,
            match (true) {
                0 === $available => 'soldOut',
                $available <= self::LAST_PIECES => 'lastPieces',
                default => 'inStock',
            },
        );
    }
}
