<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ShopArtworkView;
use App\Application\Content\ShopContentView;
use App\Domain\Shared\Locale;

final readonly class LocalizedArtwork
{
    private function __construct(
        public string $id,
        public string $url,
        public string $alt,
        public int $width,
        public int $height,
    ) {
    }

    public static function of(ShopArtworkView $artwork, Locale $locale): self
    {
        return new self($artwork->id, $artwork->url, $artwork->alt->in($locale), $artwork->width, $artwork->height);
    }

    public static function featured(ShopContentView $content, Locale $locale): ?self
    {
        $featured = array_find($content->artworks, static fn (ShopArtworkView $artwork): bool => $artwork->featured);

        return null === $featured ? null : self::of($featured, $locale);
    }

    /** @return list<self> */
    public static function gallery(ShopContentView $content, Locale $locale): array
    {
        return array_values(array_map(
            static fn (ShopArtworkView $artwork): self => self::of($artwork, $locale),
            array_filter($content->artworks, static fn (ShopArtworkView $artwork): bool => !$artwork->featured),
        ));
    }
}
