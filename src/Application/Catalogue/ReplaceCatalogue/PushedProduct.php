<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

use Symfony\Component\Uid\Ulid;

final readonly class PushedProduct
{
    public string $reference;
    public string $category;

    /**
     * @param list<PushedVariant> $variants
     */
    public function __construct(
        public Ulid $id,
        string $reference,
        public string $name,
        string $category,
        public string $displayName,
        public int $price,
        public array $variants,
    ) {
        $this->reference = trim($reference);
        $this->category = trim($category);
    }
}
