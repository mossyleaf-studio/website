<?php

declare(strict_types=1);

namespace App\Application\Catalogue\DescribeProduct;

use Symfony\Component\Uid\Ulid;

final readonly class DescribeProduct
{
    public function __construct(
        public Ulid $id,
        public string $title,
        public string $titleFr,
        public string $description,
        public string $descriptionFr,
        public string $slug,
    ) {
    }
}
