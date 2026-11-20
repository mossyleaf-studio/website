<?php

declare(strict_types=1);

namespace App\Application\Sales\DeleteTestOrder;

use App\Application\AtomicChange;
use App\Application\Sales\OrderStock;
use App\Domain\Sales\OrderRepository;

final readonly class DeleteTestOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private OrderStock $stock,
        private AtomicChange $atomicChange,
    ) {
    }

    public function __invoke(DeleteTestOrder $command): void
    {
        $this->atomicChange->apply(function () use ($command): void {
            $order = $this->orders->lock($command->id);
            $order->assertDeletable();
            if ($order->holdsUnits()) {
                $this->stock->giveBack($order);
            }
            $this->orders->remove($order);
        });
    }
}
