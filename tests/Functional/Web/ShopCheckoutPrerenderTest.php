<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Sales\OrderRepository;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ShopCheckoutPrerenderTest extends WebTestCase
{
    use ShopsOnline;

    protected function tearDown(): void
    {
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testAPendingTestOrderShowsItsStateTheWayBackToThePaymentAndAReloadLink(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);

        $crawler = $client->request('GET', '/beta/fr/order/'.$placed['token']);

        $order = self::orderOf($crawler);
        $sheet = self::sheet($crawler);
        self::assertCount(1, $sheet->filter('[data-test-order]'));
        self::assertStringContainsString(Json::string($order, 'reference'), $sheet->filter('[data-reference]')->text());
        self::assertSame($order['paymentUrl'], $sheet->filter('a[data-pay]')->attr('href'));
        self::assertSame('/beta/fr/order/'.$placed['token'], $sheet->filter('a[data-reload]')->attr('href'));
        self::assertCount(0, $sheet->filter('a[data-cart-back]'));
        self::assertLinesAndAmounts($sheet, $order, 'fr');
    }

    public function testAPaidOrderThanksTheCustomerWithoutAPaymentLink(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client, locale: 'en');
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        $crawler = $client->request('GET', '/beta/en/order/'.$placed['token']);

        $sheet = self::sheet($crawler);
        self::assertSame('paid', self::orderOf($crawler)['status']);
        self::assertCount(0, $sheet->filter('[data-tracking]'));
        self::assertCount(0, $sheet->filter('a[data-pay]'));
        self::assertCount(0, $sheet->filter('a[data-reload]'));
        self::assertCount(0, $sheet->filter('a[data-cart-back]'));
        self::assertLinesAndAmounts($sheet, self::orderOf($crawler), 'en');
    }

    public function testAShippedOrderShowsItsTrackingNumber(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client, locale: 'en');
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        $client->request('GET', '/beta/en/order/'.$placed['token']);
        $id = (string) self::getContainer()->get(OrderRepository::class)->withToken($placed['token'])?->id();

        $client->jsonRequest('POST', '/api/admin/orders/'.$id.'/shipment', ['trackingNumber' => 'LA123456789FR']);
        $sheet = self::sheet($client->request('GET', '/beta/en/order/'.$placed['token']));

        self::assertSame('LA123456789FR', $sheet->filter('[data-tracking-number]')->text());
    }

    public function testAShippedOrderWithoutATrackingNumberShowsNoTrackingLine(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client, locale: 'en');
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        $client->request('GET', '/beta/en/order/'.$placed['token']);
        $id = (string) self::getContainer()->get(OrderRepository::class)->withToken($placed['token'])?->id();

        $client->jsonRequest('POST', '/api/admin/orders/'.$id.'/shipment', ['trackingNumber' => '']);
        $sheet = self::sheet($client->request('GET', '/beta/en/order/'.$placed['token']));

        self::assertSame('shipped', self::orderOf($client->getCrawler())['status']);
        self::assertCount(0, $sheet->filter('[data-tracking]'));
    }

    public function testADeclinedOrderLinksTheCartAndNotThePayment(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::FAILED);

        $crawler = $client->request('GET', '/beta/fr/order/'.$placed['token']);

        $sheet = self::sheet($crawler);
        self::assertSame('failed', self::orderOf($crawler)['status']);
        self::assertSame('/beta/fr/cart', $sheet->filter('a[data-cart-back]')->attr('href'));
        self::assertCount(0, $sheet->filter('a[data-pay]'));
    }

    /** @param array<mixed> $order */
    private static function assertLinesAndAmounts(Crawler $sheet, array $order, string $locale): void
    {
        $lines = Json::array($order, 'lines');
        self::assertCount(\count($lines), $sheet->filter('[data-lines] li'));
        foreach ($lines as $index => $line) {
            self::assertStringContainsString(Json::string($line, 'title'), $sheet->filter('[data-lines] li')->eq($index)->text());
        }
        $money = static fn (int $cents): string => (string) (new \NumberFormatter($locale, \NumberFormatter::CURRENCY))->formatCurrency($cents / 100, 'EUR');
        self::assertSame($money(Json::int($order, 'total')), $sheet->filter('[data-total]')->text());
        self::assertSame($money(Json::int($order, 'goods')), $sheet->filter('[data-goods]')->text());
    }

    /** @return array<mixed> */
    private static function data(Crawler $crawler): array
    {
        return Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
    }

    /** @return array<mixed> */
    private static function orderOf(Crawler $crawler): array
    {
        return Json::array(self::data($crawler), 'order');
    }

    private static function sheet(Crawler $crawler): Crawler
    {
        self::assertResponseIsSuccessful();

        return $crawler->filter('.shop-prerender main [data-order]');
    }
}
