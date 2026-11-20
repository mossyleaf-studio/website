<?php

declare(strict_types=1);

namespace App\Application\Content;

final readonly class PublicSnapshot
{
    public function __construct(
        public string $content,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {
    }
}
