<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Domain\Catalogue\Product;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CatalogueProductPayload
{
    /**
     * @param list<CatalogueVariantPayload> $variants
     */
    public function __construct(
        #[Assert\NotBlank(message: 'id.invalid')]
        #[Assert\Ulid(message: 'id.invalid')]
        public string $id,
        public string $reference,
        public string $name,
        public string $category,
        public string $displayName,
        #[Assert\LessThanOrEqual(Product::MAX_PRICE)]
        public int $price,
        #[Assert\Valid]
        #[Assert\Count(min: 1)]
        public array $variants,
    ) {
    }

    public function pushed(): PushedProduct
    {
        return new PushedProduct(
            Ulid::fromString($this->id),
            $this->reference,
            $this->name,
            $this->category,
            $this->displayName,
            $this->price,
            array_map(static fn (CatalogueVariantPayload $variant): PushedVariant => $variant->pushed(), $this->variants),
        );
    }
}
