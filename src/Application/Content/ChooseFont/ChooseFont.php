<?php

declare(strict_types=1);

namespace App\Application\Content\ChooseFont;

use App\Domain\Content\FontRole;

final readonly class ChooseFont
{
    public function __construct(
        public FontRole $role,
        public string $family,
    ) {
    }
}
