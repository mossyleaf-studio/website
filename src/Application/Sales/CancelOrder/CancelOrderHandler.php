<?php

declare(strict_types=1);

namespace App\Application\Sales\CancelOrder;

use App\Application\AtomicChange;
use App\Application\Sales\OrderStock;
use App\Application\Sales\OrderView;
use App\Domain\Sales\OrderRepository;
use Psr\Clock\ClockInterface;

final readonly class CancelOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private OrderStock $stock,
        private AtomicChange $atomicChange,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CancelOrder $command): OrderView
    {
        return $this->atomicChange->apply(function () use ($command): OrderView {
            $order = $this->orders->lock($command->id);
            $order->cancel($this->clock->now());
            $this->stock->giveBack($order);

            return OrderView::of($order);
        });
    }
}
