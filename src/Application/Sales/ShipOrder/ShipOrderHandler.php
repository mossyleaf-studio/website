<?php

declare(strict_types=1);

namespace App\Application\Sales\ShipOrder;

use App\Application\AtomicChange;
use App\Application\Sales\OrderMails;
use App\Application\Sales\OrderView;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class ShipOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private AtomicChange $atomicChange,
        private OrderMails $mails,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ShipOrder $command): OrderView
    {
        $order = $this->atomicChange->apply(function () use ($command): Order {
            $order = $this->orders->lock($command->id);
            $order->ship($command->trackingNumber, $this->clock->now());

            return $order;
        });

        try {
            $this->mails->shipped($order);
        } catch (\Throwable $failure) {
            $this->logger->error('The shipped mail of an order could not be sent.', ['reference' => $order->reference(), 'failure' => $failure::class]);
        }

        return OrderView::of($order);
    }
}
