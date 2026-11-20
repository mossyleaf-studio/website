<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Domain\Catalogue\Category;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CategoryLabelPayload
{
    public function __construct(
        #[Assert\Length(max: Category::MAX_LABEL, maxMessage: 'text.too_long')]
        public string $label = '',
        #[Assert\Length(max: Category::MAX_LABEL, maxMessage: 'text.too_long')]
        public string $labelFr = '',
    ) {
    }
}
