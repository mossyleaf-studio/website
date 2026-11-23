<?php

declare(strict_types=1);

namespace App\Application\Catalogue\LabelCollection;

use Symfony\Component\Uid\Ulid;

final readonly class LabelCollection
{
    public function __construct(
        public Ulid $id,
        public string $label,
        public string $labelFr,
    ) {
    }
}
