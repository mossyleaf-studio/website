<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderProducts;

use App\Application\Catalogue\ProductView;
use App\Application\Transaction;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Shared\Ordering;

final readonly class ReorderProductsHandler
{
    public function __construct(
        private ProductRepository $products,
        private Transaction $transaction,
    ) {
    }

    /** @return list<ProductView> */
    public function __invoke(ReorderProducts $command): array
    {
        $products = $this->products->all();
        Ordering::apply($products, $command->ids);
        $this->transaction->commit();

        usort($products, static fn (Product $a, Product $b): int => $a->position() <=> $b->position());

        return ProductView::list($products);
    }
}
