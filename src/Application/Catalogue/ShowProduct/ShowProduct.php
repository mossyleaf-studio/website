<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ShowProduct;

use Symfony\Component\Uid\Ulid;

final readonly class ShowProduct
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
