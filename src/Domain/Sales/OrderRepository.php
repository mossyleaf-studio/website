<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Symfony\Component\Uid\Ulid;

interface OrderRepository
{
    public function add(Order $order): void;

    public function hasReference(string $reference): bool;

    public function withToken(string $token): ?Order;

    public function withPaymentId(string $paymentId): ?Order;

    public function lockWithPaymentId(string $paymentId): ?Order;

    /** @return array<string, int> */
    public function heldUnits(\DateTimeImmutable $now): array;

    public function testOrdersHoldingUnits(\DateTimeImmutable $now): int;

    public function get(Ulid $id): Order;

    public function lock(Ulid $id): Order;

    /** @return list<Order> */
    public function newestFirst(): array;

    /** @return list<Order> */
    public function staleHolds(\DateTimeImmutable $now): array;

    /** @return list<Order> */
    public function unpaidPlacedBefore(\DateTimeImmutable $before): array;

    public function remove(Order $order): void;

    /** @return list<Order> */
    public function lockSold(): array;

    /** @return list<OrderLine> */
    public function unexportedLines(): array;
}
