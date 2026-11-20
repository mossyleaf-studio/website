<?php

declare(strict_types=1);

namespace App\Domain\Sales;

interface OrderRepository
{
    public function add(Order $order): void;

    public function hasReference(string $reference): bool;

    public function withToken(string $token): ?Order;

    public function withPaymentId(string $paymentId): ?Order;

    public function lockWithPaymentId(string $paymentId): ?Order;

    /** @return array<string, int> */
    public function heldUnits(\DateTimeImmutable $now): array;
}
