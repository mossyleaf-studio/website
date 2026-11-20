<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderProducts;

final readonly class ReorderProducts
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
