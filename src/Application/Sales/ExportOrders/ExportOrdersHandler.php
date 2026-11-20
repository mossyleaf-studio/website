<?php

declare(strict_types=1);

namespace App\Application\Sales\ExportOrders;

use App\Application\AtomicChange;
use App\Application\Sales\ExportedOrder;
use App\Domain\Sales\OrderRepository;
use Psr\Clock\ClockInterface;

final readonly class ExportOrdersHandler
{
    public function __construct(
        private OrderRepository $orders,
        private AtomicChange $atomicChange,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<ExportedOrder> */
    public function __invoke(ExportOrders $command): array
    {
        return $this->atomicChange->apply(function (): array {
            $now = $this->clock->now();
            $sold = $this->orders->lockSold();
            foreach ($sold as $order) {
                $order->export($now);
            }

            return array_map(ExportedOrder::of(...), $sold);
        });
    }
}
