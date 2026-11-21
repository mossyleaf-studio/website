<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\AtomicChange;
use App\Application\Sales\CancelOrder\CancelOrder;
use App\Application\Sales\CancelOrder\CancelOrderHandler;
use App\Application\Sales\CheckPayment\CheckPayment;
use App\Application\Sales\CheckPayment\CheckPaymentHandler;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\DeleteTestOrder\DeleteTestOrder;
use App\Application\Sales\DeleteTestOrder\DeleteTestOrderHandler;
use App\Application\Sales\OrderStock;
use App\Application\Sales\OrderView;
use App\Application\Sales\SettleStaleOrders\SettleStaleOrdersHandler;
use App\Application\Sales\ShipOrder\ShipOrder;
use App\Application\Sales\ShipOrder\ShipOrderHandler;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderLine;
use App\Domain\Sales\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

trait MovesOrders
{
    use PlacesOrders;

    private function ship(string $token, ?string $trackingNumber = null): OrderView
    {
        $container = self::getContainer();

        return new ShipOrderHandler(
            $container->get(OrderRepository::class),
            $container->get(AtomicChange::class),
            $this->mails,
            $container->get(ClockInterface::class),
            $this->logger,
        )(new ShipOrder(self::order($token)->id(), $trackingNumber));
    }

    private function cancel(string $token): OrderView
    {
        $container = self::getContainer();

        return new CancelOrderHandler(
            $container->get(OrderRepository::class),
            self::stock(),
            $container->get(AtomicChange::class),
            $container->get(ClockInterface::class),
        )(new CancelOrder(self::order($token)->id()));
    }

    private function deleteTest(string $token): void
    {
        $container = self::getContainer();

        new DeleteTestOrderHandler(
            $container->get(OrderRepository::class),
            self::stock(),
            $container->get(AtomicChange::class),
            $container->get(ClockInterface::class),
        )(new DeleteTestOrder(self::order($token)->id()));
    }

    private function settle(): void
    {
        $container = self::getContainer();

        new SettleStaleOrdersHandler(
            $container->get(OrderRepository::class),
            $this->confirmPayment(),
            $container->get(AtomicChange::class),
            $container->get(ClockInterface::class),
        )();
    }

    private function checkPayment(string $token): OrderView
    {
        return new CheckPaymentHandler(
            self::getContainer()->get(OrderRepository::class),
            $this->confirmPayment(),
        )(new CheckPayment(self::order($token)->id()));
    }

    private function confirmPayment(): ConfirmPaymentHandler
    {
        $container = self::getContainer();

        return new ConfirmPaymentHandler(
            $this->gateway,
            $container->get(OrderRepository::class),
            $container->get(ProductRepository::class),
            $container->get(AtomicChange::class),
            $this->mails,
            $container->get(ClockInterface::class),
            $this->logger,
        );
    }

    private static function stock(): OrderStock
    {
        return new OrderStock(self::getContainer()->get(ProductRepository::class));
    }

    private function paidOrder(int $checkout, bool $preview = false): string
    {
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250, preview: $preview);
        $this->gateway->pay('checkout-'.$checkout);
        $this->confirm('checkout-'.$checkout);
        $this->mails->sent = [];

        return $placed->token;
    }

    private static function exists(string $token): bool
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return null !== self::getContainer()->get(OrderRepository::class)->withToken($token);
    }

    private static function lineCount(): int
    {
        $count = self::getContainer()->get(EntityManagerInterface::class)->createQuery('SELECT COUNT(l.id) FROM '.OrderLine::class.' l')->getSingleScalarResult();
        self::assertIsInt($count);

        return $count;
    }

    private static function fresh(string $token): Order
    {
        return self::order($token, fresh: true);
    }
}
