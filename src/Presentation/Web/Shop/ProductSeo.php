<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\PhotoView;
use App\Application\Catalogue\ShopProductView;
use App\Application\Catalogue\ShopVariantView;
use App\Domain\Shared\Locale;
use Symfony\Component\HttpFoundation\Request;

final readonly class ProductSeo
{
    private const string LINK = '/\[([^\]\n]+)\]\((https:\/\/[^\s)]+)\)/';
    private const int DESCRIPTION = 160;

    public function __construct(private ShopUrls $urls)
    {
    }

    /** @return array<string, mixed> */
    public function of(ShopProductView $product, Locale $locale, Request $request): array
    {
        $title = $product->title->in($locale);
        $description = self::plain($product->description->in($locale));
        $url = $this->urls->canonical('shop_product', $locale, ['slug' => $product->slug]);
        $cover = $product->photos[0] ?? null;
        $price = number_format($product->price / 100, 2, '.', '');

        $jsonLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title,
            'description' => $description,
            'image' => array_map(static fn (PhotoView $photo): string => $request->getUriForPath($photo->url), $product->photos),
            'url' => $url,
            'offers' => array_map(static fn (ShopVariantView $variant): array => [
                '@type' => 'Offer',
                'sku' => $variant->sku,
                'price' => $price,
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/'.($variant->soldOut ? 'OutOfStock' : 'InStock'),
                'url' => $url,
            ], $product->variants),
        ], static fn (mixed $value): bool => '' !== $value && [] !== $value);

        return [
            ...('' === $description ? [] : ['description' => $description]),
            ...(null === $cover ? [] : ['image' => ['url' => $cover->url, 'width' => $cover->width, 'height' => $cover->height, 'alt' => $title]]),
            'jsonLd' => json_encode($jsonLd, ShopPage::SAFE_IN_HTML | \JSON_THROW_ON_ERROR),
        ];
    }

    private static function plain(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace(self::LINK, '$1', $text)));

        return mb_strlen($text) > self::DESCRIPTION ? rtrim(mb_substr($text, 0, self::DESCRIPTION - 1)).'…' : $text;
    }
}
