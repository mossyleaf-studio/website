<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\Order;

final readonly class OrderStock
{
    public function __construct(private ProductRepository $products)
    {
    }

    public function giveBack(Order $order): void
    {
        $units = $order->units();
        $this->products->lock();
        foreach ($this->products->variantsWithSkus(array_map(strval(...), array_keys($units))) as $variant) {
            $variant->giveBack($units[$variant->sku()]);
        }
    }
}
