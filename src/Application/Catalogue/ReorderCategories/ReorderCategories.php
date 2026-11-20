<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderCategories;

final readonly class ReorderCategories
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
