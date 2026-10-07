<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\Typeface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class FontPayload
{
    public function __construct(
        #[Assert\Length(max: Typeface::MAX_FAMILY, maxMessage: 'text.too_long')]
        public string $family = '',
    ) {
    }
}
