<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ShowCollection;

use Symfony\Component\Uid\Ulid;

final readonly class ShowCollection
{
    public function __construct(
        public Ulid $id,
    ) {
    }
}
