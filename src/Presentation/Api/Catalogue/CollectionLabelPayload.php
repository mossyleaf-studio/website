<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Domain\Catalogue\Collection;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CollectionLabelPayload
{
    public function __construct(
        #[Assert\Length(max: Collection::MAX_LABEL, maxMessage: 'text.too_long')]
        public string $label = '',
        #[Assert\Length(max: Collection::MAX_LABEL, maxMessage: 'text.too_long')]
        public string $labelFr = '',
    ) {
    }
}
