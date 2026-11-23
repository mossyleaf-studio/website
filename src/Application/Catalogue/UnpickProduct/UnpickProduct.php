<?php

declare(strict_types=1);

namespace App\Application\Catalogue\UnpickProduct;

use Symfony\Component\Uid\Ulid;

final readonly class UnpickProduct
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
