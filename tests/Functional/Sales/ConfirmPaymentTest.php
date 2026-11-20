<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Sales\OrderQueries;
use App\Application\Sales\OrderStatusLine;
use App\Application\Sales\PaymentState;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ConfirmPaymentTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PlacesOrders;

    protected function setUp(): void
    {
        $this->doubles();
    }

    public function testAVerifiedPaymentPaysTheOrderTakesTheStockAndMailsOnceAfterTheCommit(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $clock->sleep(120);
        $this->gateway->pay('checkout-1', 'TX-MOTH');
        $outside = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getTransactionNestingLevel();

        $this->confirm('checkout-1');

        $order = self::order($placed->token, fresh: true);
        self::assertSame([OrderStatus::Paid, 'TX-MOTH'], [$order->status(), $order->transactionCode()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:02:00'), $order->paidAt());
        self::assertSame(1, self::stockOf('STI-MOTH'));
        self::assertSame(1, self::available('stickers-moth', 'STI-MOTH'));
        self::assertSame([['confirm', $placed->reference, 'paid', $outside], ['notify', $placed->reference, 'paid', $outside]], $this->mails->sent);

        $this->confirm('checkout-1');

        self::assertSame(1, self::stockOf('STI-MOTH'));
        self::assertCount(2, $this->mails->sent);
        self::assertSame(['checkout-1'], $this->gateway->asked);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:02:00'), self::order($placed->token, fresh: true)->paidAt());
    }

    public function testTheStockNeverFallsBelowZero(): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 3]], 1550);
        self::push(self::shopProducts(1));
        $this->gateway->pay('checkout-1');

        $this->confirm('checkout-1');

        self::assertSame(OrderStatus::Paid, self::order($placed->token, fresh: true)->status());
        self::assertSame(0, self::stockOf('STI-MOTH'));
    }

    public function testAPaidOrderWhoseSkuLeftTheCatalogueStillSettles(): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['PRI-FOX-A4', 1], ['STI-MOTH', 1]], 2450);
        self::push(self::shopProducts(3, foxA4: false));
        self::assertNull(self::stockOf('PRI-FOX-A4'));
        $this->gateway->pay('checkout-1');

        $this->confirm('checkout-1');

        self::assertSame(OrderStatus::Paid, self::order($placed->token, fresh: true)->status());
        self::assertSame(2, self::stockOf('STI-MOTH'));
    }

    /** @return iterable<string, array{?string, ?int, ?string}> */
    public static function mismatches(): iterable
    {
        yield 'another reference' => ['ML-0000-0000', null, null];
        yield 'another amount' => [null, 1249, null];
        yield 'another currency' => [null, null, 'GBP'];
    }

    #[DataProvider('mismatches')]
    public function testAPaymentOfAnotherReferenceAmountOrCurrencyIsNotAPayment(?string $reference, ?int $amount, ?string $currency): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->script('checkout-1', PaymentState::Paid, 'TX', $reference, $amount, $currency);

        $this->confirm('checkout-1');

        self::assertSame(OrderStatus::Pending, self::order($placed->token, fresh: true)->status());
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
        self::assertSame([['warning', $placed->reference]], array_map(static fn (array $log): array => [$log[0], $log[2]['reference'] ?? null], $this->logger->logs));
    }

    /** @return iterable<string, array{PaymentState, OrderStatus}> */
    public static function settledWithoutPayment(): iterable
    {
        yield 'failed' => [PaymentState::Failed, OrderStatus::Failed];
        yield 'expired' => [PaymentState::Expired, OrderStatus::Expired];
    }

    #[DataProvider('settledWithoutPayment')]
    public function testAFailedOrExpiredCheckoutSettlesThePendingOrderAndFreesTheHold(PaymentState $state, OrderStatus $status): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->script('checkout-1', $state);

        $this->confirm('checkout-1');

        self::assertSame($status, self::order($placed->token, fresh: true)->status());
        self::assertSame(3, self::available('stickers-moth', 'STI-MOTH'));
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame([], $this->mails->sent);
    }

    #[DataProvider('settledWithoutPayment')]
    public function testAVerifiedPaymentArrivingLateStillWins(PaymentState $state, OrderStatus $status): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->script('checkout-1', $state);
        $this->confirm('checkout-1');
        self::assertSame($status, self::order($placed->token, fresh: true)->status());

        $this->gateway->pay('checkout-1');
        $this->confirm('checkout-1');

        self::assertSame(OrderStatus::Paid, self::order($placed->token, fresh: true)->status());
        self::assertSame(1, self::stockOf('STI-MOTH'));
        self::assertCount(2, $this->mails->sent);
        self::assertSame(['checkout-1', 'checkout-1'], $this->gateway->asked);
    }

    public function testAPendingOrderPastItsTimeWithAnUnpaidCheckoutExpires(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);

        $clock->sleep(35 * 60 - 1);
        $this->confirm('checkout-1');
        self::assertSame(OrderStatus::Pending, self::order($placed->token, fresh: true)->status());

        $clock->sleep(1);
        $this->confirm('checkout-1');
        self::assertSame(OrderStatus::Expired, self::order($placed->token, fresh: true)->status());
    }

    public function testAMailThatCannotBeSentNeitherBlocksNorUndoesThePayment(): void
    {
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->pay('checkout-1');
        $this->mails->failure = new \RuntimeException('SMTP down for lou@example.test');

        $this->confirm('checkout-1');

        self::assertSame(OrderStatus::Paid, self::order($placed->token, fresh: true)->status());
        self::assertSame(1, self::stockOf('STI-MOTH'));
        self::assertSame(['confirm', 'notify'], array_column($this->mails->sent, 0));
        self::assertCount(2, $this->logger->logs);
        foreach ($this->logger->logs as [$level, $message, $context]) {
            self::assertSame('error', $level);
            self::assertSame($placed->reference, $context['reference'] ?? null);
            self::assertStringNotContainsString('lou@example.test', $message.json_encode($context));
        }
    }

    public function testAnUnknownCheckoutIsNotEvenAskedFor(): void
    {
        self::stockTheShop();

        $this->confirm('checkout-404');

        self::assertSame([], $this->gateway->asked);
    }

    public function testAnUnreachableGatewayChangesNothing(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 2]], 1250);
        $this->gateway->pay('checkout-1');
        $this->gateway->down = true;
        $clock->sleep(3600);

        try {
            $this->confirm('checkout-1');
            self::fail('The gateway is down.');
        } catch (PaymentUnavailable) {
        }

        self::assertSame(OrderStatus::Pending, self::order($placed->token, fresh: true)->status());
        self::assertSame([], $this->mails->sent);
    }

    public function testTheStatusOfAnOrderShowsNothingPersonalAndTheCheckoutOnlyWhileItCanBePaid(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['PRI-FOX-A4', 2], ['STI-MOTH', 1]], 3950, preview: true);
        $queries = new OrderQueries(self::getContainer()->get(OrderRepository::class), self::getContainer()->get(ClockInterface::class));

        $status = $queries->status($placed->token);
        self::assertNotNull($status);
        self::assertSame([$placed->reference, 'pending', true, 'https://pay.test/checkout-1', 3300, 650, 3950], [$status->reference, $status->status, $status->test, $status->paymentUrl, $status->goods, $status->shipping, $status->total]);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:00:00'), $status->placedAt);
        self::assertSame(
            [['Fox print', 'A4', 2, 1500, 3000], ['Stickers Moth', null, 1, 300, 300]],
            array_map(static fn (OrderStatusLine $line): array => [$line->title, $line->variant, $line->quantity, $line->unitPrice, $line->lineTotal], $status->lines),
        );
        self::assertSame(['reference', 'status', 'test', 'paymentUrl', 'lines', 'goods', 'shipping', 'total', 'placedAt', 'trackingNumber'], array_keys(get_object_vars($status)));
        self::assertSame('checkout-1', $queries->paymentToVerify($placed->token));

        $paymentUrl = static fn (): ?string => $queries->status($placed->token)?->paymentUrl;
        $clock->sleep(29 * 60);
        self::assertSame('https://pay.test/checkout-1', $paymentUrl());
        $clock->sleep(2 * 60);
        self::assertNull($paymentUrl());
        $clock->sleep(4 * 60);
        self::assertNull($paymentUrl());
        self::assertSame('checkout-1', $queries->paymentToVerify($placed->token));

        $this->gateway->pay('checkout-1');
        $this->confirm('checkout-1');
        self::assertSame(['paid', null], [$queries->status($placed->token)?->status, $queries->status($placed->token)?->paymentUrl]);
        self::assertNull($queries->paymentToVerify($placed->token));

        self::assertNull($queries->status('unknown'));
        self::assertNull($queries->status(str_repeat('a', 43)));
        self::assertNull($queries->paymentToVerify('unknown'));
    }
}
