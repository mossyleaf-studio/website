<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CataloguePayload
{
    /**
     * @param list<CatalogueProductPayload> $products
     */
    public function __construct(
        #[Assert\Valid]
        public array $products,
    ) {
    }

    public function command(): ReplaceCatalogue
    {
        return new ReplaceCatalogue(array_map(static fn (CatalogueProductPayload $product): PushedProduct => $product->pushed(), $this->products));
    }
}
