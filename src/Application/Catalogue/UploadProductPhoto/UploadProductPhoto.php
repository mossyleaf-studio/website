<?php

declare(strict_types=1);

namespace App\Application\Catalogue\UploadProductPhoto;

use Symfony\Component\Uid\Ulid;

final readonly class UploadProductPhoto
{
    public function __construct(
        public Ulid $productId,
        public string $path,
    ) {
    }
}
