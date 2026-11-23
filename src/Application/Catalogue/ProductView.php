<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Domain\Catalogue\Product;

final readonly class ProductView
{
    /**
     * @param list<VariantView> $variants
     * @param list<PhotoView>   $photos
     */
    private function __construct(
        public string $id,
        public string $reference,
        public string $name,
        public string $displayName,
        public CategoryView $category,
        public int $price,
        public array $variants,
        public bool $retired,
        public string $slug,
        public string $title,
        public string $titleFr,
        public string $description,
        public string $descriptionFr,
        public bool $visible,
        public bool $picked,
        public int $position,
        public array $photos,
    ) {
    }

    public static function of(Product $product): self
    {
        return new self(
            (string) $product->id(),
            $product->reference(),
            $product->name(),
            $product->displayName(),
            CategoryView::of($product->category()),
            $product->price(),
            array_map(VariantView::of(...), $product->variants()),
            $product->isRetired(),
            $product->slug(),
            $product->title()->en,
            $product->title()->fr,
            $product->description()->en,
            $product->description()->fr,
            $product->isVisible(),
            $product->isPicked(),
            $product->position(),
            array_map(PhotoView::of(...), $product->photos()),
        );
    }

    /**
     * @param list<Product> $products
     *
     * @return list<self>
     */
    public static function list(array $products): array
    {
        return array_map(self::of(...), $products);
    }
}
