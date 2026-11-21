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
        $this->products->lock();
        $taken = [];
        foreach ($order->lines() as $line) {
            $taken[$line->sku()] = ($taken[$line->sku()] ?? 0) + $line->taken();
        }
        foreach ($this->products->variantsWithSkus(array_map(strval(...), array_keys($taken))) as $variant) {
            $variant->giveBack($taken[$variant->sku()]);
        }
    }
}
