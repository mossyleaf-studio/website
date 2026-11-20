<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;

final readonly class DraftArtworkView
{
    private function __construct(
        public string $id,
        public string $url,
        public string $alt,
        public string $altFr,
        public int $width,
        public int $height,
        public bool $featured,
    ) {
    }

    public static function of(Artwork $artwork): self
    {
        return new self((string) $artwork->id(), ArtworkView::URL_PREFIX.$artwork->file(), $artwork->alt(), $artwork->altFr(), $artwork->width(), $artwork->height(), $artwork->isFeatured());
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
