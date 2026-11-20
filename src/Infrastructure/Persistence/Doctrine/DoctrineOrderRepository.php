<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Sales\Order;
use App\Domain\Sales\OrderLine;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Order $order): void
    {
        $this->entityManager->persist($order);
    }

    public function hasReference(string $reference): bool
    {
        return null !== $this->entityManager->getRepository(Order::class)->findOneBy(['reference' => $reference]);
    }

    public function withToken(string $token): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy(['token' => $token]);
    }

    public function withPaymentId(string $paymentId): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy(['paymentId' => $paymentId]);
    }

    public function lockWithPaymentId(string $paymentId): ?Order
    {
        $order = $this->entityManager
            ->createQuery('SELECT o FROM '.Order::class.' o WHERE o.paymentId = :id')
            ->setParameter('id', $paymentId)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $order instanceof Order ? $order : null;
    }

    public function heldUnits(\DateTimeImmutable $now): array
    {
        /** @var list<array{sku: string, units: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery('SELECT l.sku AS sku, SUM(l.quantity) AS units FROM '.OrderLine::class.' l JOIN l.order o WHERE o.status = :pending AND o.expiresAt > :now GROUP BY l.sku')
            ->setParameter('pending', OrderStatus::Pending->value)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => [$row['sku'], (int) $row['units']], $rows), 1, 0);
    }
}
