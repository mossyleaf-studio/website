<?php

declare(strict_types=1);

namespace App\Application\Content\EditAbout;

final readonly class EditAbout
{
    public function __construct(
        public string $title,
        public string $text,
    ) {
    }
}
