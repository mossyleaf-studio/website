<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Application\Content\LocalizedText;
use App\Domain\Catalogue\Category;
use App\Domain\Shared\Locale;

final readonly class ShopCategoryView
{
    private function __construct(
        public string $key,
        public LocalizedText $label,
        public int $position,
    ) {
    }

    public static function of(Category $category, string $key): self
    {
        return new self($key, new LocalizedText($category->labelIn(Locale::En), $category->labelIn(Locale::Fr)), $category->position());
    }

    /**
     * @param list<ShopProductView> $products
     *
     * @return list<self>
     */
    public static function shownIn(array $products): array
    {
        $categories = [];
        foreach ($products as $product) {
            $categories[$product->category->key] ??= $product->category;
        }
        usort($categories, static fn (self $a, self $b): int => [$a->position, $a->key] <=> [$b->position, $b->key]);

        return $categories;
    }
}
