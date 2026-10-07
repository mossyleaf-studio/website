<?php

declare(strict_types=1);

namespace App\Application\Content;

final readonly class ResizedImage
{
    public const string EXTENSION = 'webp';

    public function __construct(
        public string $bytes,
        public int $width,
        public int $height,
    ) {
    }
}
