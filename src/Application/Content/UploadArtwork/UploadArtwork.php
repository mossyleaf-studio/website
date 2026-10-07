<?php

declare(strict_types=1);

namespace App\Application\Content\UploadArtwork;

final readonly class UploadArtwork
{
    public function __construct(
        public string $path,
        public string $alt,
    ) {
    }
}
