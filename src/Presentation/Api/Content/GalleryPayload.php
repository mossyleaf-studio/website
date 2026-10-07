<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\SiteText;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class GalleryPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: SiteText::MAX_TITLE, maxMessage: 'text.too_long')]
        public string $title = '',
    ) {
    }
}
