<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ShowProduct;

use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\ProductRepository;

final readonly class ShowProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ShowProduct $command): ProductView
    {
        $product = $this->products->get($command->id);
        $product->show();
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
