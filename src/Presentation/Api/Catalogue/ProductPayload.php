<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Domain\Catalogue\Product;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProductPayload
{
    public function __construct(
        #[Assert\Length(max: Product::MAX_TITLE, maxMessage: 'text.too_long')]
        public string $title = '',
        #[Assert\Length(max: Product::MAX_TITLE, maxMessage: 'text.too_long')]
        public string $titleFr = '',
        #[Assert\Length(max: Product::MAX_DESCRIPTION, maxMessage: 'text.too_long')]
        public string $description = '',
        #[Assert\Length(max: Product::MAX_DESCRIPTION, maxMessage: 'text.too_long')]
        public string $descriptionFr = '',
        public string $slug = '',
    ) {
    }
}
