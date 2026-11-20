<?php

declare(strict_types=1);

namespace App\Application\Catalogue\HideProduct;

use Symfony\Component\Uid\Ulid;

final readonly class HideProduct
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
