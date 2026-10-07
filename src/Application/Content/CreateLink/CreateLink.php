<?php

declare(strict_types=1);

namespace App\Application\Content\CreateLink;

use App\Domain\Content\TapeTone;

final readonly class CreateLink
{
    public function __construct(
        public string $title,
        public string $description,
        public string $url,
        public TapeTone $tape,
    ) {
    }
}
