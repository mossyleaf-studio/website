<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\Link;
use App\Domain\Content\TapeTone;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LinkPayload
{
    public function __construct(
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Link::MAX_TITLE, maxMessage: 'text.too_long')]
        public string $title = '',
        #[Assert\NotBlank(message: 'text.required', normalizer: 'trim')]
        #[Assert\Length(max: Link::MAX_DESCRIPTION, maxMessage: 'text.too_long')]
        public string $description = '',
        #[Assert\NotBlank(message: 'url.required', normalizer: 'trim')]
        #[Assert\Url(message: 'url.invalid', protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: Link::MAX_URL, maxMessage: 'text.too_long')]
        public string $url = '',
        public TapeTone $tape = TapeTone::Leaf,
    ) {
    }
}
