<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

final readonly class PushedVariant
{
    public string $sku;

    public function __construct(
        public ?string $name,
        string $sku,
        public int $stock,
    ) {
        $this->sku = trim($sku);
    }
}
