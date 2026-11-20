<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Order;

final readonly class ExportedOrder
{
    /** @param list<ExportedLine> $lines */
    private function __construct(
        public string $reference,
        public \DateTimeImmutable $placedAt,
        public array $lines,
        public int $goods,
        public int $shipping,
        public ?\DateTimeImmutable $cancelledAt,
    ) {
    }

    public static function of(Order $order): self
    {
        return new self(
            $order->reference(),
            $order->createdAt(),
            array_map(ExportedLine::of(...), $order->lines()),
            $order->goods(),
            $order->shipping(),
            $order->cancelledAt(),
        );
    }
}
