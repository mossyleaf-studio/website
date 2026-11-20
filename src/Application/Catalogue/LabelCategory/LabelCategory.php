<?php

declare(strict_types=1);

namespace App\Application\Catalogue\LabelCategory;

use Symfony\Component\Uid\Ulid;

final readonly class LabelCategory
{
    public function __construct(
        public Ulid $id,
        public string $label,
        public string $labelFr,
    ) {
    }
}
