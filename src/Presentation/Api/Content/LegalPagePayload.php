<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\LegalPage;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LegalPagePayload
{
    public function __construct(
        #[Assert\Length(max: LegalPage::MAX_TEXT, maxMessage: 'text.too_long')]
        public string $text = '',
        #[Assert\Length(max: LegalPage::MAX_TEXT, maxMessage: 'text.too_long')]
        public string $textFr = '',
    ) {
    }
}
