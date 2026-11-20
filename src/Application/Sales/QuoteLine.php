<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Catalogue\PhotoView;

final readonly class QuoteLine
{
    public function __construct(
        public string $sku,
        public string $name,
        public string $title,
        public ?string $variant,
        public int $unitPrice,
        public int $quantity,
        public int $lineTotal,
        public string $slug,
        public ?PhotoView $photo,
        public int $available,
    ) {
    }
}
