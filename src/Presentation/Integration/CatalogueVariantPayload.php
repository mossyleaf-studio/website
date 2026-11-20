<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Domain\Catalogue\ProductVariant;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CatalogueVariantPayload
{
    public function __construct(
        public string $sku,
        #[Assert\LessThanOrEqual(ProductVariant::MAX_STOCK)]
        public int $stock,
        public ?string $name = null,
    ) {
    }

    public function pushed(): PushedVariant
    {
        return new PushedVariant($this->name, $this->sku, $this->stock);
    }
}
