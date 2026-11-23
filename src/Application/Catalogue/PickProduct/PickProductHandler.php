<?php

declare(strict_types=1);

namespace App\Application\Catalogue\PickProduct;

use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\ProductRepository;

final readonly class PickProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(PickProduct $command): ProductView
    {
        $product = $this->products->get($command->id);
        $product->pick();
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
