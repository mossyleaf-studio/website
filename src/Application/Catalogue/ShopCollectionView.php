<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Application\Content\LocalizedText;
use App\Domain\Catalogue\Collection;
use App\Domain\Shared\Locale;

final readonly class ShopCollectionView
{
    private function __construct(
        public string $key,
        public LocalizedText $label,
        public int $position,
        public bool $shown,
    ) {
    }

    public static function of(Collection $collection, string $key): self
    {
        return new self($key, new LocalizedText($collection->labelIn(Locale::En), $collection->labelIn(Locale::Fr)), $collection->position(), $collection->isShown());
    }

    /**
     * @param list<ShopProductView> $products
     *
     * @return list<self>
     */
    public static function shownIn(array $products): array
    {
        $collections = [];
        foreach ($products as $product) {
            if (null !== $product->collection) {
                $collections[$product->collection->key] ??= $product->collection;
            }
        }

        return self::ordered(array_values($collections));
    }

    /**
     * @param list<self> $collections
     *
     * @return list<self>
     */
    public static function ordered(array $collections): array
    {
        usort($collections, static fn (self $a, self $b): int => [$a->position, $a->key] <=> [$b->position, $b->key]);

        return $collections;
    }
}
