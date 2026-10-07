<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\Artwork;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AltPayload
{
    public function __construct(
        #[Assert\Length(max: Artwork::MAX_ALT, maxMessage: 'text.too_long')]
        public string $alt = '',
    ) {
    }
}
