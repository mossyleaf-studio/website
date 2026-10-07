<?php

declare(strict_types=1);

namespace App\Domain\Content;

final readonly class Logo
{
    public function __construct(
        public string $file,
        public int $width,
        public int $height,
    ) {
    }
}
