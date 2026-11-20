<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use Psr\Clock\ClockInterface;

final readonly class OrderQueries
{
    public function __construct(
        private OrderRepository $orders,
        private ClockInterface $clock,
    ) {
    }

    public function status(string $token): ?OrderStatusView
    {
        $order = $this->withToken($token);

        return null === $order ? null : OrderStatusView::of($order, $this->clock->now());
    }

    public function paymentToVerify(string $token): ?string
    {
        $order = $this->withToken($token);

        return null !== $order && OrderStatus::Pending === $order->status() ? $order->paymentId() : null;
    }

    /** @return list<OrderView> */
    public function orders(): array
    {
        return array_map(OrderView::of(...), $this->orders->newestFirst());
    }

    private function withToken(string $token): ?Order
    {
        return Order::TOKEN_LENGTH === \strlen($token) ? $this->orders->withToken($token) : null;
    }
}
