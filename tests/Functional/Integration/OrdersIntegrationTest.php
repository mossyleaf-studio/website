<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use App\Application\Sales\CartLine;
use App\Application\Sales\PaymentState;
use App\Application\Sales\PlaceOrder\PlacedOrder;
use App\Application\Sales\PlaceOrder\PlaceOrder;
use App\Application\Sales\PlaceOrder\PlaceOrderHandler;
use App\Domain\Sales\Delivery;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Locale;
use App\Tests\Functional\Sales\SellsOrders;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;

final class OrdersIntegrationTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use SellsOrders;

    private const string TOKEN = 'test-mossytrunk-token-0123456789abcdef';

    protected function setUp(): void
    {
        $this->doubles();
    }

    protected function tearDown(): void
    {
        $_ENV['MOSSYTRUNK_TOKEN'] = self::TOKEN;
        self::sumUp()->forget();

        parent::tearDown();
    }

    public function testNoOrderIsListedWhileTheShopHasSoldNothing(): void
    {
        $client = self::createClient();

        $client->request('GET', '/integrations/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseIsSuccessful();
        self::assertSame('{"orders":[]}', $client->getResponse()->getContent());
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
    }

    public function testTheOrdersAreOnlyRead(): void
    {
        $client = self::createClient();

        $client->request('POST', '/integrations/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseStatusCodeSame(405);
    }

    public function testEveryPaidOrderLeavesWithItsFiguresAndNothingPersonal(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $paid = $this->paidOrder([['PRI-FOX-A4', 1], ['STI-MOTH', 2]], 2750);
        $clock->sleep(60);
        $shipped = $this->paidOrder([['STI-MOTH', 1]], 950);
        self::ship($shipped);
        $clock->sleep(60);
        $cancelled = $this->paidOrder([['PRI-OWL', 1]], 1850);
        $clock->sleep(84480);
        self::cancel($cancelled);

        $answer = self::exportThroughTheRoute();

        $expected = ['orders' => [
            [
                'id' => $paid->reference,
                'reference' => $paid->reference,
                'placedAt' => '2026-11-20T10:00:00+00:00',
                'lines' => [
                    ['sku' => 'PRI-FOX-A4', 'name' => 'Prints Fox', 'variant' => 'A4', 'unitPrice' => 1500, 'quantity' => 1],
                    ['sku' => 'STI-MOTH', 'name' => 'Stickers Moth', 'variant' => null, 'unitPrice' => 300, 'quantity' => 2],
                ],
                'goods' => 2100,
                'shipping' => 650,
                'cancelledAt' => null,
            ],
            [
                'id' => $shipped->reference,
                'reference' => $shipped->reference,
                'placedAt' => '2026-11-20T10:01:00+00:00',
                'lines' => [['sku' => 'STI-MOTH', 'name' => 'Stickers Moth', 'variant' => null, 'unitPrice' => 300, 'quantity' => 1]],
                'goods' => 300,
                'shipping' => 650,
                'cancelledAt' => null,
            ],
            [
                'id' => $cancelled->reference,
                'reference' => $cancelled->reference,
                'placedAt' => '2026-11-20T10:02:00+00:00',
                'lines' => [['sku' => 'PRI-OWL', 'name' => 'Prints Owl', 'variant' => null, 'unitPrice' => 1200, 'quantity' => 1]],
                'goods' => 1200,
                'shipping' => 650,
                'cancelledAt' => '2026-11-21T09:30:00+00:00',
            ],
        ]];
        self::assertSame(json_encode($expected, JsonResponse::DEFAULT_ENCODING_OPTIONS), $answer);
        self::assertMatchesRegularExpression('/^ML-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $paid->reference);
        foreach (['lou@example.test', 'Lou Martin', '+33 6 12 34 56 78', 'Fougères', '75011', 'Paris', 'Fox print', $paid->token, self::paymentId($paid), 'TX-1', '"en"'] as $personal) {
            self::assertStringNotContainsString($personal, $answer);
        }
    }

    public function testOnlyPaidOrdersOfTheRealShopLeave(): void
    {
        self::stockTheShop();
        $kept = [$this->paidOrder([['STI-MOTH', 1]], 950, test: true), $this->placeOrder([['STI-MOTH', 1]], 950)];
        $this->settleAs($kept[] = $this->placeOrder([['STI-MOTH', 1]], 950), PaymentState::Failed);
        $this->settleAs($kept[] = $this->placeOrder([['STI-MOTH', 1]], 950), PaymentState::Expired);
        $sold = $this->paidOrder([['PRI-OWL', 1]], 1850);

        self::assertSame([$sold->reference], self::exportedReferences());
        self::assertNotNull(self::order($sold->token, fresh: true)->exportedAt());
        foreach ($kept as $placed) {
            self::assertNull(self::order($placed->token, fresh: true)->exportedAt());
        }
    }

    public function testTheOrdersLeaveOldestFirstThenByTheirId(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $first = $this->paidOrder([['STI-MOTH', 1]], 950);
        $clock->sleep(60);
        $second = $this->paidOrder([['STI-MOTH', 1]], 950);
        $clock->sleep(60);
        $third = $this->paidOrder([['PRI-OWL', 1]], 1850);
        self::placedAt($second, new \DateTimeImmutable('2026-11-20 09:00:00'));
        self::placedAt($third, new \DateTimeImmutable('2026-11-20 10:00:00'));

        self::assertSame([$second->reference, $first->reference, $third->reference], self::exportedReferences());
    }

    public function testTheFirstExportStampsEachOrderOnceWithTheTimeOfTheClock(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $early = $this->paidOrder([['STI-MOTH', 1]], 950);
        $clock->sleep(3600);

        $first = self::exportThroughTheRoute();

        self::assertEquals(new \DateTimeImmutable('2026-11-20 11:00:00'), self::order($early->token, fresh: true)->exportedAt());
        $clock->sleep(1800);
        $late = $this->paidOrder([['PRI-OWL', 1]], 1850);
        $clock->sleep(1800);

        $second = self::exportThroughTheRoute();

        self::assertSame([$early->reference, $late->reference], array_column(Json::array(Json::decode($second), 'orders'), 'id'));
        self::assertSame(Json::array(Json::decode($first), 'orders', 0), Json::array(Json::decode($second), 'orders', 0));
        self::assertEquals(new \DateTimeImmutable('2026-11-20 11:00:00'), self::order($early->token, fresh: true)->exportedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 12:00:00'), self::order($late->token)->exportedAt());

        $clock->sleep(3600);
        self::assertSame($second, self::exportThroughTheRoute());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 12:00:00'), self::order($late->token, fresh: true)->exportedAt());
    }

    public function testAnOrderCancelledAfterItsExportComesBackCancelled(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $placed = $this->paidOrder([['STI-MOTH', 1]], 950);
        self::assertNull(Json::at(Json::decode(self::exportThroughTheRoute()), 'orders', 0, 'cancelledAt'));
        $clock->sleep(86400);
        self::cancel($placed);

        $answer = Json::decode(self::exportThroughTheRoute());

        self::assertSame('2026-11-21T10:00:00+00:00', Json::at($answer, 'orders', 0, 'cancelledAt'));
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:00:00'), self::order($placed->token, fresh: true)->exportedAt());
    }

    public function testAnOrderWhosePaymentTheSiteMissedIsVerifiedBeforeTheSalesLeave(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $missed = self::placeAtSumUp();
        self::sumUp()->settle(self::paymentId($missed), FakeSumUp::PAID);
        $clock->sleep(36 * 60);

        self::assertSame([$missed->reference], self::exportedReferences());
        $order = self::order($missed->token, fresh: true);
        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 10:36:00'), $order->exportedAt());
    }

    public function testTheSalesStillLeaveWhileSumUpIsDown(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        self::stockTheShop();
        $paid = $this->paidOrder([['PRI-OWL', 1]], 1850);
        $pending = self::placeAtSumUp();
        $clock->sleep(36 * 60);
        self::sumUp()->goDown();

        self::assertSame([$paid->reference], self::exportedReferences());
        self::assertSame(OrderStatus::Pending, self::order($pending->token, fresh: true)->status());
    }

    private static function placeAtSumUp(): PlacedOrder
    {
        return self::getContainer()->get(PlaceOrderHandler::class)(new PlaceOrder(
            [new CartLine('STI-MOTH', 1)],
            'FR',
            new Delivery('lou@example.test', 'Lou Martin', '+33 6 12 34 56 78', '1 rue des Fougères', null, '75011', 'Paris', null),
            Locale::En,
            false,
            950,
        ));
    }

    private static function sumUp(): FakeSumUp
    {
        return new FakeSumUp(new MockHttpClient(), self::getContainer()->getParameter('kernel.share_dir').'/fake-sumup');
    }

    private static function exportThroughTheRoute(): string
    {
        $client = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->request('GET', '/integrations/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        return (string) $client->getResponse()->getContent();
    }

    /** @return list<mixed> */
    private static function exportedReferences(): array
    {
        return array_column(Json::array(Json::decode(self::exportThroughTheRoute()), 'orders'), 'reference');
    }
}
