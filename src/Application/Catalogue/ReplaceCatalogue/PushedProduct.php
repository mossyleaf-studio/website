<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

use App\Domain\Shared\Translation;
use Symfony\Component\Uid\Ulid;

final readonly class PushedProduct
{
    public string $reference;
    public string $category;
    public Translation $descriptions;

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
        ?Translation $descriptions = null,
    ) {
        $this->reference = trim($reference);
        $this->category = trim($category);
        $this->descriptions = $descriptions ?? Translation::none();
    }
}
