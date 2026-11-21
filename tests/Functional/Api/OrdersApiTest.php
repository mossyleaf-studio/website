<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Sales\CartLine;
use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\PlaceOrder\PlaceOrder;
use App\Application\Sales\PlaceOrder\PlaceOrderHandler;
use App\Domain\Sales\Delivery;
use App\Domain\Shared\Locale;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class OrdersApiTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use ShopsOnline;

    private const string UNKNOWN = '01JD3K7Q0000000000000000F0';

    protected function tearDown(): void
    {
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testTheListAnswersEveryOrderNewestFirstWithItsExactKeys(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $first = self::placeThroughTheShop($client);
        $clock->sleep(120);
        self::pay($first);
        $clock->sleep(60);
        $second = self::placeThroughTheShop($client, ['lines' => [['sku' => 'PRI-FOX-A4', 'quantity' => 1]], 'expectedTotal' => 2150], 'en');

        $orders = self::orders($client);

        self::assertSame([$second['reference'], $first['reference']], array_column($orders, 'reference'));
        $order = Json::array($orders, 1);
        self::assertMatchesRegularExpression('/^TFAKE[0-9A-F]{8}$/', Json::string($order, 'transactionCode'));
        self::assertSame([
            'id' => (string) self::order($first['token'])->id(),
            'reference' => $first['reference'],
            'status' => 'paid',
            'test' => true,
            'locale' => 'fr',
            'placedAt' => self::date('2026-11-20 10:00:00'),
            'expiresAt' => self::date('2026-11-20 10:35:00'),
            'paidAt' => self::date('2026-11-20 10:02:00'),
            'shippedAt' => null,
            'cancelledAt' => null,
            'email' => 'lou@example.test',
            'name' => 'Lou Martin',
            'phone' => '+33 6 12 34 56 78',
            'line1' => '1 rue des Fougères',
            'line2' => 'Bâtiment B',
            'postalCode' => '75011',
            'city' => 'Paris',
            'region' => null,
            'country' => 'FR',
            'zone' => 'france',
            'lines' => [['sku' => 'STI-MOTH', 'name' => 'Stickers Moth', 'title' => 'Stickers Moth', 'variant' => null, 'unitPrice' => 300, 'quantity' => 2, 'lineTotal' => 600]],
            'goods' => 600,
            'shipping' => 650,
            'total' => 1250,
            'units' => 2,
            'transactionCode' => $order['transactionCode'],
            'trackingNumber' => null,
            'hasCheckout' => true,
            'statusUrl' => '/beta/fr/order/'.$first['token'],
        ], $order);
        self::assertSame(['pending', '/beta/en/order/'.$second['token']], [Json::string($orders, 0, 'status'), Json::string($orders, 0, 'statusUrl')]);
    }

    public function testARealOrderLinksToThePublicStatusPage(): void
    {
        $client = self::stockedClient();
        $token = self::placeRealOrder();

        self::assertSame([false, '/en/order/'.$token], [Json::at(self::orders($client), 0, 'test'), Json::string(self::orders($client), 0, 'statusUrl')]);
    }

    public function testTheListSettlesTheStaleCheckoutsFirst(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $stale = self::placeThroughTheShop($client, ['lines' => [['sku' => 'STI-MOTH', 'quantity' => 1]], 'expectedTotal' => 950]);
        $paidLate = self::placeThroughTheShop($client, ['lines' => [['sku' => 'STI-MOTH', 'quantity' => 1]], 'expectedTotal' => 950]);
        self::sumUp()->settle($paidLate['paymentId'], FakeSumUp::PAID);
        $clock->sleep(36 * 60);

        $orders = self::orders($client);

        self::assertSame([$paidLate['reference'] => 'paid', $stale['reference'] => 'expired'], array_column($orders, 'status', 'reference'));
    }

    public function testTheListStillAnswersWhenSumUpIsDown(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        $clock->sleep(36 * 60);
        self::sumUp()->goDown();

        self::assertSame([$placed['reference'] => 'pending'], array_column(self::orders($client), 'status', 'reference'));
    }

    public function testAPaidOrderShipsAndIsAnsweredAsTheListGivesIt(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::pay($placed);

        $client->jsonRequest('POST', self::url($placed, '/shipment'), ['trackingNumber' => ' LA123456789FR ']);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);
        self::assertEmailHeaderSame(self::getMailerMessage() ?? throw new \LogicException('No mail'), 'Subject', '[TEST] Votre commande '.$placed['reference'].' est en route');
        $shipped = self::answer($client);
        self::assertSame(['shipped', 'LA123456789FR'], [$shipped['status'], $shipped['trackingNumber']]);
        self::assertNotNull($shipped['shippedAt']);
        self::assertSame([$shipped], self::orders($client));
    }

    public function testAnOrderShipsWithoutATrackingNumber(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::pay($placed);

        $client->jsonRequest('POST', self::url($placed, '/shipment'), ['trackingNumber' => null]);

        self::assertResponseIsSuccessful();
        self::assertSame(['shipped', null], [self::answer($client)['status'], self::answer($client)['trackingNumber']]);
    }

    public function testATrackingNumberPastedWithSpacesAroundShipsTrimmed(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::pay($placed);

        $client->jsonRequest('POST', self::url($placed, '/shipment'), ['trackingNumber' => "\tLA123456789FR\r\n"]);

        self::assertResponseIsSuccessful();
        self::assertSame('LA123456789FR', self::answer($client)['trackingNumber']);
    }

    /** @return iterable<string, array{string}> */
    public static function refusedTrackingNumbers(): iterable
    {
        yield 'too long' => [str_repeat('A', 65)];
        yield 'a line break' => ["LA123\nBcc: victim@example.test"];
        yield 'a carriage return' => ["LA123\rX"];
    }

    #[DataProvider('refusedTrackingNumbers')]
    public function testARefusedTrackingNumberShipsNothing(string $trackingNumber): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::pay($placed);

        $client->jsonRequest('POST', self::url($placed, '/shipment'), ['trackingNumber' => $trackingNumber]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['trackingNumber'], array_column(Json::array(self::answer($client), 'violations'), 'propertyPath'));
        self::assertSame('paid', Json::string(self::orders($client), 0, 'status'));
    }

    public function testARefusedMoveAnswers422WithItsCode(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);

        foreach (['/shipment', '/cancellation'] as $move) {
            $client->jsonRequest('POST', self::url($placed, $move), ['trackingNumber' => 'LA1']);

            self::assertResponseStatusCodeSame(422, $move);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('sales.order_move_refused', Json::string(self::answer($client), 'code'));
        }
    }

    public function testAPaidOrderIsCancelledAndItsUnitsComeBack(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::pay($placed);
        self::assertSame(1, self::stockOf('STI-MOTH'));

        $client->jsonRequest('POST', self::url($placed, '/cancellation'));

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', self::answer($client)['status']);
        self::assertNotNull(self::answer($client)['cancelledAt']);
        self::assertSame(3, self::stockOf('STI-MOTH'));
    }

    public function testThePaymentCheckPaysAnOrderTheSiteMissed(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::FAILED);
        self::getContainer()->get(ConfirmPaymentHandler::class)(new ConfirmPayment($placed['paymentId']));
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        $client->jsonRequest('POST', self::url($placed, '/payment-check'));

        self::assertResponseIsSuccessful();
        self::assertSame('paid', self::answer($client)['status']);
    }

    public function testThePaymentCheckAnswers503WhenSumUpIsDown(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->goDown();

        $client->jsonRequest('POST', self::url($placed, '/payment-check'));

        self::assertResponseStatusCodeSame(503);
        self::assertSame('sales.payment_unavailable', Json::string(self::answer($client), 'code'));
    }

    public function testATestOrderIsDeletedOnceItsCheckoutClosedAndARealOneRefused(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        $real = self::placeRealOrder();

        $client->jsonRequest('DELETE', '/api/admin/orders/'.self::order($real)->id());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('sales.not_a_test_order', Json::string(self::answer($client), 'code'));

        $client->jsonRequest('DELETE', self::url($placed, ''));

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('sales.checkout_still_open', Json::string(self::answer($client), 'code'));

        $clock->sleep(30 * 60);
        $client->jsonRequest('DELETE', self::url($placed, ''));

        self::assertResponseStatusCodeSame(204);
        self::assertSame([self::order($real)->reference()], array_column(self::orders($client), 'reference'));
    }

    public function testAnUnknownOrderIsNotFound(): void
    {
        $client = self::signedInClient();

        foreach ([['POST', '/shipment'], ['POST', '/cancellation'], ['POST', '/payment-check'], ['DELETE', '']] as [$method, $path]) {
            $client->jsonRequest($method, '/api/admin/orders/'.self::UNKNOWN.$path, ['trackingNumber' => null]);

            self::assertResponseStatusCodeSame(404, $method.$path);
            self::assertSame('shared.not_found', Json::string(self::answer($client), 'code'));
        }
    }

    public function testEveryRouteNeedsAnAdmin(): void
    {
        $client = self::createClient();

        foreach (self::routes() as [$method, $url]) {
            $client->jsonRequest($method, $url, ['trackingNumber' => null]);

            self::assertResponseStatusCodeSame(401, $method.' '.$url);
        }
    }

    public function testTheWritesRefuseAnotherOrigin(): void
    {
        $client = self::signedInClient();

        foreach (\array_slice(self::routes(), 1) as [$method, $url]) {
            $client->jsonRequest($method, $url, ['trackingNumber' => null], ['HTTP_ORIGIN' => 'https://evil.test']);

            self::assertResponseStatusCodeSame(403, $method.' '.$url);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function ordersPages(): iterable
    {
        yield 'the landing' => ['/admin'];
        yield 'the landing with a slash' => ['/admin/'];
        yield 'the list' => ['/admin/orders'];
        yield 'one order' => ['/admin/orders/'.self::UNKNOWN];
    }

    #[DataProvider('ordersPages')]
    public function testTheAdminOpensOnTheOrders(string $page): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);

        $crawler = $client->request('GET', $page);

        self::assertResponseIsSuccessful();
        $preloaded = Json::decode($crawler->filter('#app-preload')->text());
        self::assertSame(['/api/admin/publication', '/api/admin/shop/readiness', '/api/admin/orders'], array_keys($preloaded));
        self::assertSame([$placed['reference']], array_column(Json::array($preloaded, '/api/admin/orders'), 'reference'));
    }

    public function testTheTextsPageStillPreloadsTheTexts(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/admin/texts');

        self::assertSame(['/api/admin/publication', '/api/admin/shop/readiness', '/api/admin/texts'], array_keys(Json::decode($crawler->filter('#app-preload')->text())));
    }

    /** @return list<array{string, string}> */
    private static function routes(): array
    {
        return [
            ['GET', '/api/admin/orders'],
            ['POST', '/api/admin/orders/'.self::UNKNOWN.'/shipment'],
            ['POST', '/api/admin/orders/'.self::UNKNOWN.'/cancellation'],
            ['POST', '/api/admin/orders/'.self::UNKNOWN.'/payment-check'],
            ['DELETE', '/api/admin/orders/'.self::UNKNOWN],
        ];
    }

    /** @return list<array<mixed>> */
    private static function orders(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/admin/orders');
        self::assertResponseIsSuccessful();

        return array_values(array_map(static fn (mixed $order): array => \is_array($order) ? $order : [], self::answer($client)));
    }

    /** @param array{token: string, paymentId: string} $placed */
    private static function pay(array $placed): void
    {
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::getContainer()->get(ConfirmPaymentHandler::class)(new ConfirmPayment($placed['paymentId']));
    }

    /** @param array{token: string} $placed */
    private static function url(array $placed, string $path): string
    {
        return '/api/admin/orders/'.self::order($placed['token'])->id().$path;
    }

    private static function placeRealOrder(): string
    {
        return self::getContainer()->get(PlaceOrderHandler::class)(new PlaceOrder([new CartLine('STI-MOTH', 1)], 'FR', new Delivery(...self::CUSTOMER, region: null), Locale::En, false, 950))->token;
    }

    private static function date(string $date): string
    {
        return new \DateTimeImmutable($date)->format(\DATE_ATOM);
    }
}
