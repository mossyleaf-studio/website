<?php

declare(strict_types=1);

namespace App\Application\Catalogue\DeleteProductPhoto;

use App\Application\Content\MediaSweeper;
use App\Application\Transaction;
use App\Domain\Catalogue\ProductRepository;

final readonly class DeleteProductPhotoHandler
{
    public function __construct(
        private ProductRepository $products,
        private MediaSweeper $media,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteProductPhoto $command): void
    {
        $this->products->get($command->productId)->removePhoto($command->photoId);
        $this->transaction->commit();
        $this->media->sweep();
    }
}
