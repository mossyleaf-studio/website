<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\PublicPage;

final readonly class PublicSnapshot
{
    public function __construct(
        public PublicPage $page,
        public string $content,
    ) {
    }
}
