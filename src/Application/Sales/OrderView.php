<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Order;

final readonly class OrderView
{
    /** @param list<OrderLineView> $lines */
    private function __construct(
        public string $id,
        public string $reference,
        public string $token,
        public string $status,
        public bool $test,
        public string $locale,
        public \DateTimeImmutable $placedAt,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $paidAt,
        public ?\DateTimeImmutable $shippedAt,
        public ?\DateTimeImmutable $cancelledAt,
        public string $email,
        public string $name,
        public ?string $phone,
        public string $line1,
        public ?string $line2,
        public string $postalCode,
        public string $city,
        public ?string $region,
        public string $country,
        public string $zone,
        public array $lines,
        public int $goods,
        public int $shipping,
        public int $total,
        public int $units,
        public ?string $transactionCode,
        public ?string $trackingNumber,
        public bool $hasCheckout,
    ) {
    }

    public static function of(Order $order): self
    {
        return new self(
            (string) $order->id(),
            $order->reference(),
            $order->token(),
            $order->status()->value,
            $order->isTest(),
            $order->locale()->value,
            $order->createdAt(),
            $order->expiresAt(),
            $order->paidAt(),
            $order->shippedAt(),
            $order->cancelledAt(),
            $order->email(),
            $order->name(),
            $order->phone(),
            $order->line1(),
            $order->line2(),
            $order->postalCode(),
            $order->city(),
            $order->region(),
            $order->country(),
            $order->zone()->value,
            array_map(OrderLineView::of(...), $order->lines()),
            $order->goods(),
            $order->shipping(),
            $order->total(),
            array_sum($order->units()),
            $order->transactionCode(),
            $order->trackingNumber(),
            null !== $order->paymentId(),
        );
    }
}
