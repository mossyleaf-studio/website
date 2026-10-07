<?php

declare(strict_types=1);

namespace App\Application\Content;

final readonly class FontFile
{
    public function __construct(
        public string $unicodeRange,
        public string $bytes,
    ) {
    }
}
