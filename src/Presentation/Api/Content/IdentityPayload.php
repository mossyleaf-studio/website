<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\SiteText;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class IdentityPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: SiteText::MAX_STUDIO_NAME, maxMessage: 'text.too_long')]
        public string $studioName = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: SiteText::MAX_INTRO, maxMessage: 'text.too_long')]
        public string $intro = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: SiteText::MAX_META_DESCRIPTION, maxMessage: 'text.too_long')]
        public string $metaDescription = '',
        #[Assert\Length(max: SiteText::MAX_SEARCH_TITLE, maxMessage: 'text.too_long')]
        public string $searchTitle = '',
    ) {
    }
}
