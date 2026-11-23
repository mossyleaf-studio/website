<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Application\Content\LocalizedText;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductVariant;
use App\Domain\Shared\Locale;

final readonly class ShopProductView
{
    /**
     * @param list<PhotoView>       $photos
     * @param list<ShopVariantView> $variants
     */
    private function __construct(
        public string $id,
        public string $slug,
        public string $displayName,
        public LocalizedText $title,
        public LocalizedText $description,
        public int $price,
        public ShopCategoryView $category,
        public array $photos,
        public array $variants,
        public bool $hidden,
        public bool $soldOut,
        public bool $picked,
    ) {
    }

    /** @param array<string, int> $held */
    public static function of(Product $product, ShopCategoryView $category, array $held): self
    {
        $several = \count($product->variants()) > 1;
        $variants = array_map(static fn (ProductVariant $variant): ShopVariantView => ShopVariantView::of($variant, $several, $held[$variant->sku()] ?? 0), $product->variants());

        return new self(
            (string) $product->id(),
            $product->slug(),
            $product->displayName(),
            new LocalizedText(self::titleIn($product, Locale::En), self::titleIn($product, Locale::Fr)),
            new LocalizedText($product->description()->in(Locale::En), $product->description()->in(Locale::Fr)),
            $product->price(),
            $category,
            array_map(PhotoView::of(...), $product->photos()),
            $variants,
            !$product->isVisible(),
            null === array_find($variants, static fn (ShopVariantView $variant): bool => !$variant->soldOut),
            $product->isPicked(),
        );
    }

    private static function titleIn(Product $product, Locale $locale): string
    {
        $title = $product->title()->in($locale);

        return '' === $title ? $product->displayName() : $title;
    }
}
