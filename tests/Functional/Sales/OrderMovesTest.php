<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Sales\PaymentState;
use App\Domain\Sales\Exception\NotATestOrder;
use App\Domain\Sales\Exception\OrderMoveRefused;
use App\Domain\Sales\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class OrderMovesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use MovesOrders;

    protected function setUp(): void
    {
        $this->doubles();
    }

    public function testAPaidOrderShipsWithItsTrimmedTrackingNumberAndMailsOnceAfterTheCommit(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $token = $this->paidOrder(1);
        $clock->sleep(3600);
        $outside = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getTransactionNestingLevel();

        $view = $this->ship($token, '  LA123456789FR ');

        $order = self::fresh($token);
        self::assertSame([OrderStatus::Shipped, 'LA123456789FR'], [$order->status(), $order->trackingNumber()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 11:00:00'), $order->shippedAt());
        self::assertSame(['shipped', 'LA123456789FR'], [$view->status, $view->trackingNumber]);
        self::assertSame([['shipped', $order->reference(), 'shipped', $outside]], $this->mails->sent);
        self::assertSame(1, self::stockOf('STI-MOTH'));
    }

    public function testAnOrderShipsWithoutATrackingNumber(): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1);

        $this->ship($token, '   ');

        self::assertSame([OrderStatus::Shipped, null], [self::fresh($token)->status(), self::fresh($token)->trackingNumber()]);
        self::assertSame(['shipped'], array_column($this->mails->sent, 0));
    }

    public function testAShippedOrderDoesNotShipTwice(): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1);
        $this->ship($token, 'LA1');

        try {
            $this->ship($token, 'LA2');
            self::fail('A shipped order ships once.');
        } catch (OrderMoveRefused) {
        }

        self::assertSame('LA1', self::fresh($token)->trackingNumber());
        self::assertCount(1, $this->mails->sent);
    }

    /** @return iterable<string, array{?PaymentState, OrderStatus}> */
    public static function unpaid(): iterable
    {
        yield 'pending' => [null, OrderStatus::Pending];
        yield 'failed' => [PaymentState::Failed, OrderStatus::Failed];
        yield 'expired' => [PaymentState::Expired, OrderStatus::Expired];
    }

    #[DataProvider('unpaid')]
    public function testAnUnpaidOrderNeitherShipsNorCancels(?PaymentState $state, OrderStatus $status): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        if (null !== $state) {
            $this->gateway->script('checkout-1', $state);
            $this->confirm('checkout-1');
        }

        foreach ([fn () => $this->ship($placed->token, 'LA1'), fn () => $this->cancel($placed->token)] as $move) {
            try {
                $move();
                self::fail('An unpaid order cannot move.');
            } catch (OrderMoveRefused) {
            }
        }

        self::assertSame($status, self::fresh($placed->token)->status());
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
    }

    public function testAMailThatCannotBeSentLeavesTheOrderShippedAndLogsNothingPersonal(): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1);
        $this->mails->failure = new \RuntimeException('SMTP down for lou@example.test');

        $this->ship($token, 'LA1');

        $order = self::fresh($token);
        self::assertSame(OrderStatus::Shipped, $order->status());
        self::assertCount(1, $this->logger->logs);
        [$level, $message, $context] = $this->logger->logs[0];
        self::assertSame(['error', $order->reference()], [$level, $context['reference'] ?? null]);
        self::assertStringNotContainsString('lou@example.test', $message.json_encode($context));
        self::assertStringNotContainsString('Lou', $message.json_encode($context));
    }

    public function testCancellingAPaidOrderGivesItsUnitsBackWithoutAnyMail(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $token = $this->paidOrder(1);
        self::assertSame(1, self::stockOf('STI-MOTH'));
        $clock->sleep(60);

        $view = $this->cancel($token);

        $order = self::fresh($token);
        self::assertSame(OrderStatus::Cancelled, $order->status());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:01:00'), $order->cancelledAt());
        self::assertSame('cancelled', $view->status);
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
    }

    public function testCancellingAShippedOrderGivesItsUnitsBack(): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1);
        $this->ship($token, 'LA1');
        $this->mails->sent = [];

        $this->cancel($token);

        self::assertSame(OrderStatus::Cancelled, self::fresh($token)->status());
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
    }

    public function testAnOrderIsCancelledOnceAndItsUnitsComeBackOnce(): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1);
        $this->cancel($token);

        try {
            $this->cancel($token);
            self::fail('A cancelled order is cancelled once.');
        } catch (OrderMoveRefused) {
        }

        self::assertSame(3, self::stockOf('STI-MOTH'));
    }

    public function testASkuThatLeftTheCatalogueIsSkippedWhenTheUnitsComeBack(): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['PRI-FOX-A4', 1], ['STI-MOTH', 2]], 2750);
        $this->gateway->pay('checkout-1');
        $this->confirm('checkout-1');
        self::push(self::shopProducts(1, foxA4: false));

        $this->cancel($placed->token);

        self::assertSame(OrderStatus::Cancelled, self::fresh($placed->token)->status());
        self::assertNull(self::stockOf('PRI-FOX-A4'));
        self::assertSame(3, self::stockOf('STI-MOTH'));
    }

    public function testARealOrderIsNeverDeleted(): void
    {
        self::stockTheShop();
        self::push(self::shopProducts(10));
        $pending = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
        $paid = $this->paidOrder(2);
        $cancelled = $this->paidOrder(3);
        $this->cancel($cancelled);

        foreach ([$pending, $paid, $cancelled] as $token) {
            try {
                $this->deleteTest($token);
                self::fail('Only a test order can be deleted.');
            } catch (NotATestOrder) {
            }
            self::assertTrue(self::exists($token));
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function holdingTestOrders(): iterable
    {
        yield 'paid' => [false];
        yield 'shipped' => [true];
    }

    #[DataProvider('holdingTestOrders')]
    public function testAPaidOrShippedTestOrderGivesItsUnitsBackAndGoesWithItsLines(bool $shipped): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1, preview: true);
        if ($shipped) {
            $this->ship($token);
        }
        $this->mails->sent = [];
        self::assertSame(1, self::stockOf('STI-MOTH'));

        $this->deleteTest($token);

        self::assertFalse(self::exists($token));
        self::assertSame(0, self::lineCount());
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
    }

    /** @return iterable<string, array{?PaymentState}> */
    public static function emptyHandedTestOrders(): iterable
    {
        yield 'pending' => [null];
        yield 'failed' => [PaymentState::Failed];
        yield 'expired' => [PaymentState::Expired];
    }

    #[DataProvider('emptyHandedTestOrders')]
    public function testAnUnpaidTestOrderGivesNothingBack(?PaymentState $state): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250, preview: true);
        if (null !== $state) {
            $this->gateway->script('checkout-1', $state);
            $this->confirm('checkout-1');
        }

        $this->deleteTest($placed->token);

        self::assertFalse(self::exists($placed->token));
        self::assertSame(0, self::lineCount());
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame(3, self::available('stickers-moth', 'STI-MOTH'));
    }

    public function testACancelledTestOrderGivesNothingBackASecondTime(): void
    {
        self::stockTheShop();
        $token = $this->paidOrder(1, preview: true);
        $this->cancel($token);

        $this->deleteTest($token);

        self::assertFalse(self::exists($token));
        self::assertSame(3, self::stockOf('STI-MOTH'));
    }
}
