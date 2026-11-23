<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\PhotoView;
use App\Application\Catalogue\ShopCategoryView;
use App\Application\Catalogue\ShopProductView;
use App\Domain\Shared\Locale;

final readonly class CatalogueProps
{
    public function __construct(private ShopUrls $urls)
    {
    }

    /**
     * @param list<ShopProductView> $products
     *
     * @return list<array<string, mixed>>
     */
    public function cards(array $products, Locale $locale, bool $preview): array
    {
        return array_map(fn (ShopProductView $product): array => [
            'title' => $product->title->in($locale),
            'url' => $this->urls->to('shop_product', $locale, $preview, ['slug' => $product->slug]),
            'price' => $product->price,
            'image' => self::cover($product),
            'soldOut' => $product->soldOut,
            ...($preview ? ['hidden' => $product->hidden] : []),
        ], $products);
    }

    /** @return array{key: string, label: string, url: string} */
    public function category(ShopCategoryView $category, Locale $locale, bool $preview): array
    {
        return ['key' => $category->key, 'label' => $category->label->in($locale), 'url' => $this->urls->to('shop_category', $locale, $preview, ['category' => $category->key])];
    }

    /**
     * @param list<ShopProductView> $products
     *
     * @return list<array<string, mixed>>
     */
    public function tiles(array $products, Locale $locale, bool $preview): array
    {
        return array_map(function (ShopCategoryView $category) use ($products, $locale, $preview): array {
            $pictured = array_find($products, static fn (ShopProductView $product): bool => $product->category->key === $category->key && [] !== $product->photos);

            return [...$this->category($category, $locale, $preview), 'image' => null === $pictured ? null : self::cover($pictured)];
        }, ShopCategoryView::shownIn($products));
    }

    /** @return array{src: string, srcset: string, width: int, height: int, alt: string}|null */
    public static function cover(ShopProductView $product): ?array
    {
        return [] === $product->photos ? null : self::image($product->photos[0], '');
    }

    /** @return array{src: string, srcset: string, width: int, height: int, alt: string} */
    public static function image(PhotoView $photo, string $alt): array
    {
        $srcset = $photo->thumbWidth < $photo->width ? \sprintf('%s %dw, %s %dw', $photo->thumbUrl, $photo->thumbWidth, $photo->url, $photo->width) : \sprintf('%s %dw', $photo->url, $photo->width);

        return ['src' => $photo->thumbUrl, 'srcset' => $srcset, 'width' => $photo->width, 'height' => $photo->height, 'alt' => $alt];
    }
}
