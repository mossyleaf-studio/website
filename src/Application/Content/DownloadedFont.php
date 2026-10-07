<?php

declare(strict_types=1);

namespace App\Application\Content;

final readonly class DownloadedFont
{
    public const string EXTENSION = 'woff2';

    /** @param list<FontFile> $files */
    public function __construct(
        public string $family,
        public int $weight,
        public array $files,
    ) {
    }
}
