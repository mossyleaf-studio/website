<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Domain\Content\SiteText;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShopNotesPayload
{
    public function __construct(
        #[Assert\Length(max: SiteText::MAX_ANNOUNCEMENT, maxMessage: 'text.too_long')]
        public string $announcement = '',
        #[Assert\Length(max: SiteText::MAX_ANNOUNCEMENT, maxMessage: 'text.too_long')]
        public string $announcementFr = '',
        #[Assert\Length(max: SiteText::MAX_PRICE_NOTE, maxMessage: 'text.too_long')]
        public string $priceNote = '',
        #[Assert\Length(max: SiteText::MAX_PRICE_NOTE, maxMessage: 'text.too_long')]
        public string $priceNoteFr = '',
    ) {
    }
}
