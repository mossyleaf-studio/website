<?php

declare(strict_types=1);

namespace App\Application\Content\EditHomeNote;

final readonly class EditHomeNote
{
    public function __construct(
        public string $title,
        public string $text,
    ) {
    }
}
