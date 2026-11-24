<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Country;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Exception\CheckoutStillOpen;
use App\Domain\Sales\Exception\InvalidTrackingNumber;
use App\Domain\Sales\Exception\NotATestOrder;
use App\Domain\Sales\Exception\OrderMoveRefused;
use App\Domain\Sales\Exception\RegionRequired;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use App\Domain\Shared\Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    private const array ALLOWED = [
        'pending' => ['paid', 'failed', 'expired'],
        'paid' => ['shipped', 'cancelled'],
        'shipped' => ['cancelled'],
        'failed' => ['paid'],
        'expired' => ['paid'],
        'cancelled' => [],
    ];

    /** @return iterable<string, array{OrderStatus, OrderStatus, bool}> */
    public static function moves(): iterable
    {
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                yield $from->value.' to '.$to->value => [$from, $to, \in_array($to->value, self::ALLOWED[$from->value], true)];
            }
        }
    }

    #[DataProvider('moves')]
    public function testTheStatusMachine(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->allows($to));
    }

    public function testAPlacedOrderIsPendingHeldForThirtyFiveMinutesWithTotalsComputedFromItsLines(): void
    {
        $now = new \DateTimeImmutable('2026-11-20 10:00:00');

        $order = self::order($now);

        self::assertSame(OrderStatus::Pending, $order->status());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $order->token());
        self::assertSame([3300, 650, 3950], [$order->goods(), $order->shipping(), $order->total()]);
        self::assertSame([3000, 300], array_map(static fn ($line): int => $line->total(), $order->lines()));
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:35:00'), $order->expiresAt());
        self::assertTrue($order->isHeldAt($now->modify('+34 minutes')));
        self::assertFalse($order->isHeldAt($now->modify('+35 minutes')));
    }

    public function testTheDeliveryFormIsTrimmedAndOptionalFieldsAreNullWhenEmpty(): void
    {
        $order = self::order(new \DateTimeImmutable(), delivery: new Delivery(' lou@example.test ', ' Lou Martin ', ' ', ' 1 rue des Fougères ', '', ' 75011 ', ' Paris ', ' '));

        self::assertSame(
            ['lou@example.test', 'Lou Martin', null, '1 rue des Fougères', null, '75011', 'Paris', null],
            [$order->email(), $order->name(), $order->phone(), $order->line1(), $order->line2(), $order->postalCode(), $order->city(), $order->region()],
        );
    }

    public function testARequiredFieldCannotBeEmptyAndNoFieldIsTooLong(): void
    {
        foreach (['email', 'name', 'line1', 'postal_code', 'city'] as $index => $field) {
            $values = ['lou@example.test', 'Lou', '1 rue', '75011', 'Paris'];
            $values[$index] = ' ';
            try {
                new Delivery($values[0], $values[1], null, $values[2], null, $values[3], $values[4], null);
                self::fail($field);
            } catch (EmptyText $exception) {
                self::assertSame(['field' => $field], $exception->parameters());
            }
        }

        $this->expectException(TextTooLong::class);
        new Delivery('lou@example.test', 'Lou', null, '1 rue', null, '75011', str_repeat('a', Delivery::MAX_CITY + 1), null);
    }

    public function testAWorldDeliveryNeedsARegion(): void
    {
        $delivery = new Delivery('lou@example.test', 'Lou', null, '1 Main St', null, '10001', 'New York', 'NY');
        self::assertSame('NY', Order::place('ML-AB12-CD34', false, Locale::En, $delivery, Country::of('US'), Zone::World, self::lines(), 1800, new \DateTimeImmutable())->region());

        $this->expectException(RegionRequired::class);
        Order::place('ML-AB12-CD34', false, Locale::En, new Delivery('lou@example.test', 'Lou', null, '1 Main St', null, '10001', 'New York', ''), Country::of('US'), Zone::World, self::lines(), 1800, new \DateTimeImmutable());
    }

    public function testPayingSetsTheDateAndTheTransactionCodeAndHappensOnce(): void
    {
        $order = self::order(new \DateTimeImmutable());
        $paidAt = new \DateTimeImmutable('2026-11-20 10:12:00');

        $order->pay('TX-1', $paidAt);

        self::assertSame([OrderStatus::Paid, 'TX-1'], [$order->status(), $order->transactionCode()]);
        self::assertEquals($paidAt, $order->paidAt());
        self::assertFalse($order->isHeldAt($paidAt));

        $this->expectException(OrderMoveRefused::class);
        $order->pay('TX-2', $paidAt);
    }

    public function testAFailedOrAnExpiredOrderIsPaidLate(): void
    {
        $failed = self::order(new \DateTimeImmutable());
        $failed->fail();
        $expired = self::order(new \DateTimeImmutable());
        $expired->expire();

        self::assertSame([OrderStatus::Failed, OrderStatus::Expired], [$failed->status(), $expired->status()]);
        $failed->pay(null, new \DateTimeImmutable());
        $expired->pay('TX', new \DateTimeImmutable());
        self::assertSame([OrderStatus::Paid, OrderStatus::Paid], [$failed->status(), $expired->status()]);
    }

    public function testAPaidOrderCannotFailOrExpire(): void
    {
        $order = self::order(new \DateTimeImmutable());
        $order->pay('TX', new \DateTimeImmutable());

        foreach ([$order->fail(...), $order->expire(...)] as $move) {
            try {
                $move();
                self::fail('A paid order must stay paid.');
            } catch (OrderMoveRefused $exception) {
                self::assertSame('paid', $exception->parameters()['from']);
            }
        }
        self::assertSame(OrderStatus::Paid, $order->status());
    }

    public function testShippingAPaidOrderTrimsTheTrackingNumberAndDatesIt(): void
    {
        $shippedAt = new \DateTimeImmutable('2026-11-21 09:00:00');
        $order = self::paid();

        $order->ship('  LA123456789FR  ', $shippedAt);

        self::assertSame([OrderStatus::Shipped, 'LA123456789FR'], [$order->status(), $order->trackingNumber()]);
        self::assertEquals($shippedAt, $order->shippedAt());
    }

    public function testTheTrackingNumberIsOptional(): void
    {
        foreach ([null, '', '   '] as $trackingNumber) {
            $order = self::paid();

            $order->ship($trackingNumber, new \DateTimeImmutable());

            self::assertSame([OrderStatus::Shipped, null], [$order->status(), $order->trackingNumber()]);
        }
    }

    public function testATrackingNumberTooLongIsRefusedAndShipsNothing(): void
    {
        $order = self::paid();
        $order->ship(str_repeat('A', Order::MAX_CODE), new \DateTimeImmutable());
        self::assertSame(Order::MAX_CODE, \strlen((string) $order->trackingNumber()));

        $order = self::paid();
        try {
            $order->ship(str_repeat('A', Order::MAX_CODE + 1), new \DateTimeImmutable());
            self::fail('A tracking number holds 64 characters at most.');
        } catch (TextTooLong $exception) {
            self::assertSame(['field' => 'tracking_number', 'max' => Order::MAX_CODE], $exception->parameters());
        }
        self::assertSame([OrderStatus::Paid, null, null], [$order->status(), $order->trackingNumber(), $order->shippedAt()]);
    }

    public function testATrackingNumberWithALineBreakIsRefused(): void
    {
        $order = self::paid();

        $this->expectException(InvalidTrackingNumber::class);
        $order->ship("LA123\nBcc: victim@example.test", new \DateTimeImmutable());
    }

    /** @return iterable<string, array{\Closure(Order): void, string}> */
    public static function notPaid(): iterable
    {
        yield 'pending' => [static fn (Order $order): null => null, 'pending'];
        yield 'failed' => [static fn (Order $order) => $order->fail(), 'failed'];
        yield 'expired' => [static fn (Order $order) => $order->expire(), 'expired'];
        yield 'shipped' => [static fn (Order $order) => $order->ship(null, new \DateTimeImmutable()), 'shipped'];
        yield 'cancelled' => [static fn (Order $order) => $order->cancel(new \DateTimeImmutable()), 'cancelled'];
    }

    /** @param \Closure(Order): void $reach */
    #[DataProvider('notPaid')]
    public function testOnlyAPaidOrderShips(\Closure $reach, string $status): void
    {
        $order = 'pending' === $status || 'failed' === $status || 'expired' === $status ? self::order(new \DateTimeImmutable()) : self::paid();
        $reach($order);

        try {
            $order->ship('LA1', new \DateTimeImmutable());
            self::fail('Only a paid order ships.');
        } catch (OrderMoveRefused $exception) {
            self::assertSame(['from' => $status, 'to' => 'shipped'], $exception->parameters());
        }
        self::assertSame($status, $order->status()->value);
    }

    public function testAPaidOrAShippedOrderIsCancelledOnce(): void
    {
        $cancelledAt = new \DateTimeImmutable('2026-11-22 09:00:00');
        $paid = self::paid();
        $shipped = self::paid();
        $shipped->ship('LA1', new \DateTimeImmutable());

        $paid->cancel($cancelledAt);
        $shipped->cancel($cancelledAt);

        self::assertSame([OrderStatus::Cancelled, OrderStatus::Cancelled], [$paid->status(), $shipped->status()]);
        self::assertEquals([$cancelledAt, $cancelledAt], [$paid->cancelledAt(), $shipped->cancelledAt()]);
        $this->expectException(OrderMoveRefused::class);
        $paid->cancel($cancelledAt);
    }

    public function testAnUnpaidOrderCannotBeCancelled(): void
    {
        $order = self::order(new \DateTimeImmutable());

        $this->expectException(OrderMoveRefused::class);
        $order->cancel(new \DateTimeImmutable());
    }

    public function testOnlyAPaidOrAShippedOrderHoldsUnits(): void
    {
        $order = self::order(new \DateTimeImmutable());
        self::assertSame(['PRI-FOX-A4' => 2, 'STI-MOTH' => 1], $order->units());
        self::assertFalse($order->holdsUnits());
        $order->pay('TX', new \DateTimeImmutable());
        self::assertTrue($order->holdsUnits());
        $order->ship(null, new \DateTimeImmutable());
        self::assertTrue($order->holdsUnits());
        $order->cancel(new \DateTimeImmutable());
        self::assertFalse($order->holdsUnits());
    }

    public function testOnlyATestOrderMayBeDeleted(): void
    {
        $placedAt = new \DateTimeImmutable('2026-11-20 10:00:00');
        self::order($placedAt, test: true)->assertDeletable($placedAt->modify('+30 minutes'));

        $this->expectException(NotATestOrder::class);
        self::order($placedAt)->assertDeletable($placedAt->modify('+30 minutes'));
    }

    public function testATestOrderIsNotDeletedWhileItsCheckoutCanStillBePaid(): void
    {
        $placedAt = new \DateTimeImmutable('2026-11-20 10:00:00');
        $order = self::order($placedAt, test: true);

        try {
            $order->assertDeletable($placedAt->modify('+29 minutes 59 seconds'));
            self::fail('A checkout that can still be paid keeps its order.');
        } catch (CheckoutStillOpen $refusal) {
            self::assertSame(['reference' => 'ML-AB12-CD34'], $refusal->parameters());
        }

        $order->assertDeletable($placedAt->modify('+30 minutes'));
        $order->pay('TX', $placedAt->modify('+1 minute'));
        $order->assertDeletable($placedAt->modify('+1 minute'));
        $this->addToAssertionCount(1);
    }

    public function testTheFirstExportStampsTheOrderAndALaterOneKeepsThatStamp(): void
    {
        $order = self::order(new \DateTimeImmutable('2026-11-20 10:00:00'));
        self::assertNull($order->exportedAt());

        $order->export(new \DateTimeImmutable('2026-11-20 11:00:00'));
        $order->export(new \DateTimeImmutable('2026-11-20 12:00:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-20 11:00:00'), $order->exportedAt());
    }

    private static function paid(): Order
    {
        $order = self::order(new \DateTimeImmutable());
        $order->pay('TX', new \DateTimeImmutable());

        return $order;
    }

    private static function order(\DateTimeImmutable $now, bool $test = false, ?Delivery $delivery = null): Order
    {
        return Order::place(
            'ML-AB12-CD34',
            $test,
            Locale::Fr,
            $delivery ?? new Delivery('lou@example.test', 'Lou', null, '1 rue des Fougères', null, '75011', 'Paris', null),
            Country::of('FR'),
            Zone::France,
            self::lines(),
            650,
            $now,
        );
    }

    /** @return list<array{sku: string, name: string, title: string, variant: ?string, unitPrice: int, quantity: int}> */
    private static function lines(): array
    {
        return [
            ['sku' => 'PRI-FOX-A4', 'name' => 'Prints Fox', 'title' => 'Affiche renard', 'variant' => 'A4', 'unitPrice' => 1500, 'quantity' => 2],
            ['sku' => 'STI-MOTH', 'name' => 'Stickers Moth', 'title' => 'Stickers Moth', 'variant' => null, 'unitPrice' => 300, 'quantity' => 1],
        ];
    }
}
