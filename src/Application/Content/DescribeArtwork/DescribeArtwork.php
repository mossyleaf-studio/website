<?php

declare(strict_types=1);

namespace App\Application\Content\DescribeArtwork;

use Symfony\Component\Uid\Ulid;

final readonly class DescribeArtwork
{
    public function __construct(
        public Ulid $id,
        public string $alt,
    ) {
    }
}
