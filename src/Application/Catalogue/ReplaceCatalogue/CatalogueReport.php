<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

final readonly class CatalogueReport
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $retired,
    ) {
    }
}
