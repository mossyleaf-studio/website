<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderProductPhotos;

use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\ProductRepository;

final readonly class ReorderProductPhotosHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ReorderProductPhotos $command): ProductView
    {
        $product = $this->products->get($command->productId);
        $product->reorderPhotos($command->ids);
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
