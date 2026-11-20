<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Order;

final readonly class OrderStatusView
{
    /** @param list<OrderStatusLine> $lines */
    private function __construct(
        public string $reference,
        public string $status,
        public bool $test,
        public ?string $paymentUrl,
        public array $lines,
        public int $goods,
        public int $shipping,
        public int $total,
        public \DateTimeImmutable $placedAt,
    ) {
    }

    public static function of(Order $order, \DateTimeImmutable $now): self
    {
        return new self(
            $order->reference(),
            $order->status()->value,
            $order->isTest(),
            $order->canBePaidAt($now) ? $order->paymentUrl() : null,
            array_map(OrderStatusLine::of(...), $order->lines()),
            $order->goods(),
            $order->shipping(),
            $order->total(),
            $order->createdAt(),
        );
    }
}
