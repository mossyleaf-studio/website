<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;

final readonly class ArtworkView
{
    public const string URL_PREFIX = '/media/artworks/';

    private function __construct(
        public string $id,
        public string $url,
        public string $alt,
        public int $width,
        public int $height,
        public bool $featured,
    ) {
    }

    public static function of(Artwork $artwork): self
    {
        return new self((string) $artwork->id(), self::URL_PREFIX.$artwork->file(), $artwork->alt(), $artwork->width(), $artwork->height(), $artwork->isFeatured());
    }

    /**
     * @param list<Artwork> $artworks
     *
     * @return list<self>
     */
    public static function list(array $artworks): array
    {
        return array_map(self::of(...), $artworks);
    }
}
