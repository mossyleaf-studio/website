<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

final readonly class PhotoFile
{
    public function __construct(
        public string $file,
        public int $width,
        public int $height,
    ) {
    }
}
