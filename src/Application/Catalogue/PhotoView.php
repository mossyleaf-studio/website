<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Application\Content\ArtworkView;
use App\Domain\Catalogue\ProductPhoto;

final readonly class PhotoView
{
    private function __construct(
        public string $id,
        public string $url,
        public int $width,
        public int $height,
        public string $thumbUrl,
        public int $thumbWidth,
        public int $thumbHeight,
    ) {
    }

    public static function of(ProductPhoto $photo): self
    {
        return new self((string) $photo->id(), ArtworkView::URL_PREFIX.$photo->file(), $photo->width(), $photo->height(), ArtworkView::URL_PREFIX.$photo->thumbFile(), $photo->thumbWidth(), $photo->thumbHeight());
    }
}
