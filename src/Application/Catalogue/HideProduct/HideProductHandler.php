<?php

declare(strict_types=1);

namespace App\Application\Catalogue\HideProduct;

use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\ProductRepository;

final readonly class HideProductHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(HideProduct $command): ProductView
    {
        $product = $this->products->get($command->id);
        $product->hide();
        $this->transaction->commit();

        return ProductView::of($product);
    }
}
