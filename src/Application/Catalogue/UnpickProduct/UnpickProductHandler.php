<?php

declare(strict_types=1);

namespace App\Application\Catalogue\UnpickProduct;

use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\ProductRepository;

final readonly class UnpickProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UnpickProduct $command): ProductView
    {
        $product = $this->products->get($command->id);
        $product->unpick();
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
