<?php

declare(strict_types=1);

namespace App\Application\Content\ChooseFonts;

use App\Domain\Content\SiteFont;

final readonly class ChooseFonts
{
    public function __construct(
        public SiteFont $heading,
        public SiteFont $body,
    ) {
    }
}
