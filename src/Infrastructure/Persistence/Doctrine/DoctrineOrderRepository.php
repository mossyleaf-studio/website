<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Sales\Order;
use App\Domain\Sales\OrderLine;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

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

    public function testOrdersHoldingUnits(\DateTimeImmutable $now): int
    {
        return (int) $this->entityManager
            ->createQuery('SELECT COUNT(o.id) FROM '.Order::class.' o WHERE o.test = true AND (o.status IN (:holding) OR (o.status = :pending AND o.expiresAt > :now))')
            ->setParameter('holding', [OrderStatus::Paid->value, OrderStatus::Shipped->value])
            ->setParameter('pending', OrderStatus::Pending->value)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getSingleScalarResult();
    }

    public function get(Ulid $id): Order
    {
        return $this->entityManager->find(Order::class, $id) ?? throw new NotFound('order', (string) $id);
    }

    public function lock(Ulid $id): Order
    {
        $order = $this->entityManager
            ->createQuery('SELECT o FROM '.Order::class.' o WHERE o.id = :id')
            ->setParameter('id', $id, UlidType::NAME)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $order instanceof Order ? $order : throw new NotFound('order', (string) $id);
    }

    public function newestFirst(): array
    {
        return $this->entityManager->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function staleHolds(\DateTimeImmutable $now): array
    {
        /** @var list<Order> $orders */
        $orders = $this->entityManager
            ->createQuery('SELECT o FROM '.Order::class.' o WHERE o.status = :pending AND o.expiresAt <= :now ORDER BY o.createdAt ASC, o.id ASC')
            ->setParameter('pending', OrderStatus::Pending->value)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getResult();

        return $orders;
    }

    public function unpaidPlacedBefore(\DateTimeImmutable $before): array
    {
        /** @var list<Order> $orders */
        $orders = $this->entityManager
            ->createQuery('SELECT o FROM '.Order::class.' o WHERE o.status IN (:unpaid) AND o.createdAt < :before ORDER BY o.createdAt ASC, o.id ASC')
            ->setParameter('unpaid', [OrderStatus::Failed->value, OrderStatus::Expired->value])
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->getResult();

        return $orders;
    }

    public function remove(Order $order): void
    {
        $this->entityManager->remove($order);
    }

    public function lockSold(): array
    {
        /** @var list<Order> $orders */
        $orders = $this->entityManager
            ->createQuery('SELECT o, l FROM '.Order::class.' o JOIN o.lines l WHERE o.status IN (:sold) AND o.test = false ORDER BY o.createdAt ASC, o.id ASC, l.position ASC')
            ->setParameter('sold', [OrderStatus::Paid->value, OrderStatus::Shipped->value, OrderStatus::Cancelled->value])
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        return $orders;
    }

    public function unexportedUnits(): array
    {
        /** @var list<array{sku: string, units: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery('SELECT l.sku AS sku, SUM(l.quantity) AS units FROM '.OrderLine::class.' l JOIN l.order o WHERE o.status IN (:holding) AND o.exportedAt IS NULL GROUP BY l.sku')
            ->setParameter('holding', [OrderStatus::Paid->value, OrderStatus::Shipped->value])
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => [$row['sku'], (int) $row['units']], $rows), 1, 0);
    }
}
