<?php

declare(strict_types=1);

namespace App\Application\Catalogue\PickProduct;

use Symfony\Component\Uid\Ulid;

final readonly class PickProduct
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
