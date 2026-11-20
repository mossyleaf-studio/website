<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Sales\PaymentState;
use App\Domain\Sales\Country;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SettleStaleOrdersTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use MovesOrders;

    protected function setUp(): void
    {
        $this->doubles();
    }

    public function testAStalePendingOrderPaidAtSumUpBecomesPaid(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->pay('checkout-1', 'TX-LATE');
        $clock->sleep(36 * 60);

        $this->settle();

        self::assertSame([OrderStatus::Paid, 'TX-LATE'], [self::fresh($placed->token)->status(), self::fresh($placed->token)->transactionCode()]);
        self::assertSame(1, self::stockOf('STI-MOTH'));
        self::assertSame(['confirm', 'notify'], array_column($this->mails->sent, 0));
    }

    public function testAStalePendingOrderNotPaidExpires(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $clock->sleep(35 * 60);

        $this->settle();

        self::assertSame(OrderStatus::Expired, self::fresh($placed->token)->status());
        self::assertSame(['checkout-1'], $this->gateway->asked);
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
    }

    public function testAStalePendingOrderWithoutACheckoutExpiresWithoutAnyCall(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $token = self::withoutCheckout();
        $clock->sleep(35 * 60);

        $this->settle();

        self::assertSame(OrderStatus::Expired, self::fresh($token)->status());
        self::assertSame([], $this->gateway->asked);
    }

    public function testAPendingOrderWhoseHoldStillRunsIsLeftAlone(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $token = self::withoutCheckout();
        $clock->sleep(35 * 60 - 1);

        $this->settle();

        self::assertSame([OrderStatus::Pending, OrderStatus::Pending], [self::fresh($placed->token)->status(), self::fresh($token)->status()]);
        self::assertSame([], $this->gateway->asked);
    }

    public function testARunAsksTheGatewayTwentyTimesAtMostOldestFirst(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        self::push(self::shopProducts(30));
        $tokens = [];
        foreach (range(1, 21) as $ignored) {
            $tokens[] = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
            $clock->sleep(1);
        }
        $clock->sleep(36 * 60);

        $this->settle();

        self::assertSame(array_map(static fn (int $number): string => 'checkout-'.$number, range(1, 20)), $this->gateway->asked);
        self::assertSame(OrderStatus::Expired, self::fresh($tokens[19])->status());
        self::assertSame(OrderStatus::Pending, self::fresh($tokens[20])->status());

        $this->settle();

        self::assertSame(OrderStatus::Expired, self::fresh($tokens[20])->status());
    }

    public function testARunStopsAskingOnceFiveSecondsHavePassed(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $tokens = [];
        foreach (range(1, 3) as $ignored) {
            $tokens[] = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
        }
        $clock->sleep(36 * 60);
        $this->gateway->answering = static fn () => $clock->sleep(3);

        $this->settle();

        self::assertSame(['checkout-1', 'checkout-2'], $this->gateway->asked);
        self::assertSame(OrderStatus::Pending, self::fresh($tokens[2])->status());
    }

    public function testARunStopsAtTheFirstGatewayFailureAndDeletesNothingItCouldNotVerify(): void
    {
        $clock = self::mockTime('2026-11-01 10:00:00');
        self::stockTheShop();
        $old = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
        $this->gateway->script('checkout-1', PaymentState::Failed);
        $this->confirm('checkout-1');
        $clock->sleep(20 * 86400);
        $stale = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
        $clock->sleep(36 * 60);
        $this->gateway->asked = [];
        $this->gateway->down = true;

        $this->settle();

        self::assertSame(['checkout-2'], $this->gateway->asked);
        self::assertSame(OrderStatus::Pending, self::fresh($stale)->status());
        self::assertTrue(self::exists($old));
        self::assertSame(OrderStatus::Failed, self::fresh($old)->status());
    }

    /** @return iterable<string, array{PaymentState}> */
    public static function unpaidStates(): iterable
    {
        yield 'failed' => [PaymentState::Failed];
        yield 'expired' => [PaymentState::Expired];
    }

    #[DataProvider('unpaidStates')]
    public function testAnUnpaidOrderPlacedMoreThanFourteenDaysAgoIsDeletedAndAYoungerOneKept(PaymentState $state): void
    {
        $clock = self::mockTime('2026-11-01 10:00:00');
        self::stockTheShop();
        $old = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
        $this->gateway->script('checkout-1', $state);
        $this->confirm('checkout-1');
        $clock->sleep(86400);
        $young = $this->placeOrder([['STI-MOTH', 1]], 950)->token;
        $this->gateway->script('checkout-2', $state);
        $this->confirm('checkout-2');
        $clock->sleep(13 * 86400 + 1);
        $this->gateway->asked = [];

        $this->settle();

        self::assertFalse(self::exists($old));
        self::assertTrue(self::exists($young));
        self::assertSame(1, self::lineCount());
        self::assertSame(['checkout-1'], $this->gateway->asked);
    }

    public function testAnOldOrderThatNeverHadACheckoutIsDeletedWithoutAnyCall(): void
    {
        $clock = self::mockTime('2026-11-01 10:00:00');
        self::stockTheShop();
        $token = self::withoutCheckout();
        $clock->sleep(15 * 86400);

        $this->settle();

        self::assertFalse(self::exists($token));
        self::assertSame([], $this->gateway->asked);
    }

    public function testAnOldFailedOrderThatSumUpNowReportsPaidBecomesPaidInsteadOfBeingDeleted(): void
    {
        $clock = self::mockTime('2026-11-01 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->script('checkout-1', PaymentState::Failed);
        $this->confirm('checkout-1');
        $clock->sleep(15 * 86400);
        $this->gateway->pay('checkout-1', 'TX-LATE');

        $this->settle();

        self::assertTrue(self::exists($placed->token));
        self::assertSame(OrderStatus::Paid, self::fresh($placed->token)->status());
        self::assertSame(1, self::stockOf('STI-MOTH'));
        self::assertSame(['confirm', 'notify'], array_column($this->mails->sent, 0));
    }

    public function testAPaidShippedOrCancelledOrderIsNeverTouchedWhateverItsAge(): void
    {
        $clock = self::mockTime('2026-01-01 10:00:00');
        self::stockTheShop();
        self::push(self::shopProducts(10));
        $paid = $this->paidOrder(1);
        $shipped = $this->paidOrder(2);
        $this->ship($shipped, 'LA1');
        $cancelled = $this->paidOrder(3);
        $this->cancel($cancelled);
        $clock->sleep(300 * 86400);
        $this->gateway->asked = [];

        $this->settle();

        self::assertSame(
            [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Cancelled],
            [self::fresh($paid)->status(), self::fresh($shipped)->status(), self::fresh($cancelled)->status()],
        );
        self::assertSame([], $this->gateway->asked);
    }

    public function testThePaymentCheckPaysAFailedOrderThatSumUpReportsPaid(): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->script('checkout-1', PaymentState::Failed);
        $this->confirm('checkout-1');
        $this->gateway->pay('checkout-1', 'TX-MISSED');

        $view = $this->checkPayment($placed->token);

        self::assertSame(['paid', 'TX-MISSED', true], [$view->status, $view->transactionCode, $view->hasCheckout]);
        self::assertSame(OrderStatus::Paid, self::fresh($placed->token)->status());
        self::assertSame(1, self::stockOf('STI-MOTH'));
    }

    public function testThePaymentCheckLetsAnUnreachableSumUpBeKnown(): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->down = true;

        $this->expectException(PaymentUnavailable::class);
        $this->checkPayment($placed->token);
    }

    private static function withoutCheckout(): string
    {
        $container = self::getContainer();
        $order = Order::place(
            'ML-0000-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)),
            false,
            Locale::En,
            new Delivery('lou@example.test', 'Lou Martin', null, '1 rue des Fougères', null, '75011', 'Paris', null),
            Country::of('FR'),
            Zone::France,
            [['sku' => 'STI-MOTH', 'name' => 'Stickers Moth', 'title' => 'Stickers Moth', 'variant' => null, 'unitPrice' => 300, 'quantity' => 1]],
            650,
            $container->get(ClockInterface::class)->now(),
        );
        $container->get(OrderRepository::class)->add($order);
        $container->get(EntityManagerInterface::class)->flush();

        return $order->token();
    }
}
