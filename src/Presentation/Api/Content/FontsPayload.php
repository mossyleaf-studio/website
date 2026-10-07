<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\SiteFont;

final readonly class FontsPayload
{
    public function __construct(
        public SiteFont $heading = SiteFont::Gaegu,
        public SiteFont $body = SiteFont::Kalam,
    ) {
    }
}
