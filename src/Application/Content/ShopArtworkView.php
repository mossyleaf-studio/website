<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Artwork;

final readonly class ShopArtworkView
{
    public function __construct(
        public string $id,
        public string $url,
        public LocalizedText $alt,
        public int $width,
        public int $height,
        public bool $featured,
    ) {
    }

    public static function of(Artwork $artwork): self
    {
        return new self(
            (string) $artwork->id(),
            ArtworkView::URL_PREFIX.$artwork->file(),
            LocalizedText::of($artwork->alt(), $artwork->altFr()),
            $artwork->width(),
            $artwork->height(),
            $artwork->isFeatured(),
        );
    }
}
