<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderCollections;

final readonly class ReorderCollections
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
    ) {
    }
}
