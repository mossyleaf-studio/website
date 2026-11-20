<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Sales\CancelOrder\CancelOrder;
use App\Application\Sales\CancelOrder\CancelOrderHandler;
use App\Application\Sales\DeleteTestOrder\DeleteTestOrder;
use App\Application\Sales\DeleteTestOrder\DeleteTestOrderHandler;
use App\Application\Sales\ExportedOrder;
use App\Application\Sales\ExportOrders\ExportOrders;
use App\Application\Sales\ExportOrders\ExportOrdersHandler;
use App\Application\Sales\PaymentState;
use App\Application\Sales\PlaceOrder\PlacedOrder;
use App\Application\Sales\ShipOrder\ShipOrder;
use App\Application\Sales\ShipOrder\ShipOrderHandler;
use App\Domain\Sales\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

trait SellsOrders
{
    use PlacesOrders;

    /** @param list<array{string, int}> $lines */
    private function paidOrder(array $lines, int $expectedTotal, bool $test = false): PlacedOrder
    {
        $placed = $this->placeOrder($lines, $expectedTotal, preview: $test);
        $this->gateway->pay(self::paymentId($placed));
        $this->confirm(self::paymentId($placed));

        return $placed;
    }

    private function settleAs(PlacedOrder $placed, PaymentState $state): void
    {
        $this->gateway->script(self::paymentId($placed), $state);
        $this->confirm(self::paymentId($placed));
    }

    private static function paymentId(PlacedOrder $placed): string
    {
        return self::order($placed->token)->paymentId() ?? throw new \LogicException('No checkout opened');
    }

    private static function ship(PlacedOrder $placed): void
    {
        self::getContainer()->get(ShipOrderHandler::class)(new ShipOrder(self::order($placed->token)->id(), null));
    }

    private static function cancel(PlacedOrder $placed): void
    {
        self::getContainer()->get(CancelOrderHandler::class)(new CancelOrder(self::order($placed->token)->id()));
    }

    private static function deleteTestOrder(PlacedOrder $placed): void
    {
        self::getContainer()->get(DeleteTestOrderHandler::class)(new DeleteTestOrder(self::order($placed->token)->id()));
    }

    private static function placedAt(PlacedOrder $placed, \DateTimeImmutable $at): void
    {
        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('UPDATE '.Order::class.' o SET o.createdAt = :at WHERE o.token = :token')
            ->setParameter('at', $at, Types::DATETIME_IMMUTABLE)
            ->setParameter('token', $placed->token)
            ->execute();
    }

    /** @return list<ExportedOrder> */
    private static function export(): array
    {
        return self::getContainer()->get(ExportOrdersHandler::class)(new ExportOrders());
    }
}
