<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderProductPhotos;

use Symfony\Component\Uid\Ulid;

final readonly class ReorderProductPhotos
{
    /** @param list<string> $ids */
    public function __construct(
        public Ulid $productId,
        public array $ids,
    ) {
    }
}
