<?php

declare(strict_types=1);

namespace App\Application\Catalogue\HideCollection;

use Symfony\Component\Uid\Ulid;

final readonly class HideCollection
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
