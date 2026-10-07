<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Logo;

final readonly class ImageView
{
    private function __construct(
        public string $url,
        public int $width,
        public int $height,
    ) {
    }

    public static function ofLogo(?Logo $logo): ?self
    {
        return null === $logo ? null : new self(ArtworkView::URL_PREFIX.$logo->file, $logo->width, $logo->height);
    }
}
