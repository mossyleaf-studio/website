<?php

declare(strict_types=1);

namespace App\Tests\Functional\Sales;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Sales\CartLine;
use App\Application\Sales\CartPricer;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\Exception\CartOutdated;
use App\Domain\Sales\Exception\EmptyCart;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderLine;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PlaceOrderTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PlacesOrders;

    protected function setUp(): void
    {
        $this->doubles();
    }

    public function testAPendingOrderCopiesTheLinesTheZoneAndTheTotalsThenOpensTheCheckout(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();

        $placed = $this->placeOrder([['PRI-FOX-A4', 2], ['STI-MOTH', 1]], 3950, 'FR', locale: Locale::Fr);

        $order = self::order($placed->token);
        self::assertSame([$order->reference(), 'https://pay.test/checkout-1'], [$placed->reference, $placed->paymentUrl]);
        self::assertMatchesRegularExpression('/^ML-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $placed->reference);
        self::assertSame(43, \strlen($placed->token));
        self::assertSame([OrderStatus::Pending, false, Locale::Fr, 'FR', Zone::France], [$order->status(), $order->isTest(), $order->locale(), $order->country(), $order->zone()]);
        self::assertSame([3300, 650, 3950], [$order->goods(), $order->shipping(), $order->total()]);
        self::assertSame(
            [['PRI-FOX-A4', 'Prints Fox', 'Affiche renard', 'A4', 1500, 2], ['STI-MOTH', 'Stickers Moth', 'Stickers Moth', null, 300, 1]],
            array_map(static fn (OrderLine $line): array => [$line->sku(), $line->name(), $line->title(), $line->variant(), $line->unitPrice(), $line->quantity()], $order->lines()),
        );
        self::assertSame(
            ['lou@example.test', 'Lou Martin', '+33 6 12 34 56 78', '1 rue des Fougères', 'Bâtiment B', '75011', 'Paris', null],
            [$order->email(), $order->name(), $order->phone(), $order->line1(), $order->line2(), $order->postalCode(), $order->city(), $order->region()],
        );
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:00:00'), $order->createdAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:35:00'), $order->expiresAt());
        self::assertSame(['checkout-1', 'https://pay.test/checkout-1'], [$order->paymentId(), $order->paymentUrl()]);
        self::assertTrue(self::getContainer()->get(OrderRepository::class)->hasReference($placed->reference));

        self::assertCount(1, $this->gateway->opened);
        $opened = $this->gateway->opened[0];
        self::assertSame([$placed->reference, 3950, 'https://shop.test/webhooks/sumup', 'https://shop.test/fr/order/'.$placed->token], [$opened['reference'], $opened['amount'], $opened['webhookUrl'], $opened['redirectUrl']]);
        self::assertStringContainsString($placed->reference, $opened['description']);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:30:00'), $opened['validUntil']);
    }

    public function testAnOrderPlacedThroughThePreviewIsATestOrderAndMaySellAHiddenProduct(): void
    {
        self::stockTheShop();

        $placed = $this->placeOrder([['CAR-BEE', 1]], 1150, preview: true);

        self::assertTrue(self::order($placed->token)->isTest());
        self::assertSame('https://shop.test/beta/en/order/'.$placed->token, $this->gateway->opened[0]['redirectUrl']);
        self::assertStringContainsString('TEST', $this->gateway->opened[0]['description']);
    }

    public function testAWorldOrderKeepsItsRegion(): void
    {
        self::stockTheShop();

        $placed = $this->placeOrder([['STI-MOTH', 1]], 2100, 'US', delivery: new Delivery('lou@example.test', 'Lou', null, '1 Main St', null, '10001', 'New York', 'NY'));

        self::assertSame([Zone::World, 'US', 'NY'], [self::order($placed->token)->zone(), self::order($placed->token)->country(), self::order($placed->token)->region()]);
    }

    /** @return iterable<string, array{list<array{string, int}>, int, string}> */
    public static function outdatedCarts(): iterable
    {
        yield 'another total' => [[['STI-MOTH', 1]], 951, 'FR'];
        yield 'a line removed' => [[['STI-MOTH', 1], ['PRI-FOX-A5', 1]], 950, 'FR'];
        yield 'a quantity reduced' => [[['STI-MOTH', 4]], 1550, 'FR'];
        yield 'a country of a zone that is off' => [[['STI-MOTH', 1]], 300, 'DE'];
    }

    /** @param list<array{string, int}> $lines */
    #[DataProvider('outdatedCarts')]
    public function testAnOutdatedCartStoresNothingAndOpensNoCheckout(array $lines, int $expectedTotal, string $country): void
    {
        self::stockTheShop();

        try {
            $this->placeOrder($lines, $expectedTotal, $country);
            self::fail('The cart should be outdated.');
        } catch (CartOutdated $exception) {
            self::assertSame('sales.cart_outdated', $exception->getMessage());
        }

        self::assertSame(0, self::orderCount());
        self::assertSame([], $this->gateway->opened);
    }

    public function testAnEmptyCartIsRefused(): void
    {
        self::stockTheShop();

        $this->expectException(EmptyCart::class);

        try {
            $this->placeOrder([], 650);
        } finally {
            self::assertSame(0, self::orderCount());
        }
    }

    public function testAGatewayFailureLeavesAFailedOrderThatHoldsNothing(): void
    {
        self::stockTheShop();
        $this->gateway->down = true;

        try {
            $this->placeOrder([['STI-MOTH', 2]], 1250);
            self::fail('The payment should be unavailable.');
        } catch (PaymentUnavailable $exception) {
            self::assertSame('sales.payment_unavailable', $exception->getMessage());
        }

        self::assertSame(1, self::orderCount());
        self::assertSame([OrderStatus::Failed], array_map(static fn (Order $order): OrderStatus => $order->status(), self::getContainer()->get(EntityManagerInterface::class)->getRepository(Order::class)->findAll()));
        self::assertSame(3, self::available('stickers-moth', 'STI-MOTH'));
        self::assertSame(3, self::stockOf('STI-MOTH'));
    }

    public function testThePendingUnitsAreHeldOnTheProductPageAndInTheQuoteWithoutTouchingTheStock(): void
    {
        self::stockTheShop();

        $this->placeOrder([['STI-MOTH', 2]], 1250);

        self::assertSame(1, self::available('stickers-moth', 'STI-MOTH'));
        self::assertSame(1, self::available('stickers-moth', 'STI-MOTH', true));
        self::assertSame('lastPieces', self::getContainer()->get(CatalogueQueries::class)->shopProduct('stickers-moth', false)?->variants[0]->state);
        self::assertSame(3, self::stockOf('STI-MOTH'));

        $quote = self::getContainer()->get(CartPricer::class)->quote([new CartLine('STI-MOTH', 3)], 'FR', false, Locale::En);
        self::assertSame([1, 1], [$quote->lines[0]->quantity, $quote->lines[0]->available]);
        self::assertSame('reduced', $quote->issues[0]->type);
    }

    public function testAnExpiredHoldFreesItsUnitsAtOnceWhateverTheStoredStatus(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->placeOrder([['STI-MOTH', 3]], 1550);
        self::assertSame(0, self::available('stickers-moth', 'STI-MOTH'));

        $clock->sleep(35 * 60 - 1);
        self::assertSame(0, self::available('stickers-moth', 'STI-MOTH'));

        $clock->sleep(1);
        self::assertSame(3, self::available('stickers-moth', 'STI-MOTH'));
        self::assertSame(OrderStatus::Pending, self::order($placed->token)->status());
    }

    public function testTheLastUnitCannotBeOrderedTwice(): void
    {
        self::stockTheShop();

        $this->placeOrder([['PRI-OWL', 1]], 1850);

        $this->expectException(CartOutdated::class);
        $this->placeOrder([['PRI-OWL', 1]], 1850);
    }

    public function testACataloguePushDuringAPendingOrderKeepsTheHold(): void
    {
        self::stockTheShop();
        $this->placeOrder([['STI-MOTH', 2]], 1250);

        self::push(self::shopProducts(5));

        self::assertSame(5, self::stockOf('STI-MOTH'));
        self::assertSame(3, self::available('stickers-moth', 'STI-MOTH'));
    }
}
