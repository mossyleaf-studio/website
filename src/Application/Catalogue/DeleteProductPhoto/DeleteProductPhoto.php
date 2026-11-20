<?php

declare(strict_types=1);

namespace App\Application\Catalogue\DeleteProductPhoto;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteProductPhoto
{
    public function __construct(
        public Ulid $productId,
        public Ulid $photoId,
    ) {
    }
}
