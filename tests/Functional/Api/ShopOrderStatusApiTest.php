<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Domain\Sales\OrderStatus;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class ShopOrderStatusApiTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use ShopsOnline;

    protected function tearDown(): void
    {
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testTheStatusAnswersThePublicStateOfTheOrderAndNothingPersonal(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client, ['lines' => [['sku' => 'PRI-FOX-A4', 'quantity' => 1], ['sku' => 'STI-MOTH', 'quantity' => 2]], 'expectedTotal' => 2750]);

        $client->jsonRequest('GET', '/beta/api/shop/fr/orders/'.$placed['token']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertSame([
            'reference' => $placed['reference'],
            'status' => 'pending',
            'test' => true,
            'paymentUrl' => $placed['paymentUrl'],
            'lines' => [
                ['title' => 'Affiche renard', 'variant' => 'A4', 'quantity' => 1, 'unitPrice' => 1500, 'lineTotal' => 1500],
                ['title' => 'Stickers Moth', 'variant' => null, 'quantity' => 2, 'unitPrice' => 300, 'lineTotal' => 600],
            ],
            'goods' => 2100,
            'shipping' => 650,
            'total' => 2750,
            'placedAt' => new \DateTimeImmutable('2026-11-20 10:00:00')->format(\DATE_ATOM),
            'trackingNumber' => null,
        ], self::answer($client));
        $content = (string) $client->getResponse()->getContent();
        foreach ([...self::CUSTOMER, 'lou', 'Fougères', $placed['token'], 'PRI-FOX-A4'] as $personal) {
            self::assertStringNotContainsString($personal, $content);
        }
    }

    public function testAShippedOrderShowsItsTrackingNumberUntilItIsCancelled(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::getContainer()->get(ConfirmPaymentHandler::class)(new ConfirmPayment($placed['paymentId']));
        $id = (string) self::order($placed['token'])->id();
        $client->jsonRequest('GET', '/beta/api/shop/fr/orders/'.$placed['token']);
        self::assertNull(self::answer($client)['trackingNumber']);

        $client->jsonRequest('POST', '/api/admin/orders/'.$id.'/shipment', ['trackingNumber' => 'LA123456789FR']);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('GET', '/beta/api/shop/fr/orders/'.$placed['token']);

        self::assertSame(['shipped', 'LA123456789FR'], [self::answer($client)['status'], self::answer($client)['trackingNumber']]);
        self::assertSame('trackingNumber', array_key_last(self::answer($client)));
        $content = (string) $client->getResponse()->getContent();
        foreach ([...self::CUSTOMER, 'lou', 'Fougères', $placed['token'], 'STI-MOTH'] as $personal) {
            self::assertStringNotContainsString($personal, $content);
        }

        $client->jsonRequest('POST', '/api/admin/orders/'.$id.'/cancellation');
        $client->jsonRequest('GET', '/beta/api/shop/fr/orders/'.$placed['token']);

        self::assertSame(['cancelled', null], [self::answer($client)['status'], self::answer($client)['trackingNumber']]);
    }

    public function testItSettlesAPaidCheckoutWithoutAnyWebhook(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(['paid', null], [self::answer($client)['status'], self::answer($client)['paymentUrl']]);
        self::assertSame(OrderStatus::Paid, self::order($placed['token'], fresh: true)->status());
        self::assertSame(1, self::stockOf('STI-MOTH'));
    }

    public function testADeclinedCheckoutFailsTheOrder(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::FAILED);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);

        self::assertSame(['failed', null], [self::answer($client)['status'], self::answer($client)['paymentUrl']]);
    }

    public function testADeclinedCheckoutPaidOnRetryIsShownPaid(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::FAILED);
        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);
        self::assertSame(OrderStatus::Failed, self::order($placed['token'], fresh: true)->status());
        $clock->sleep(29 * 60);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);

        self::assertSame(['paid', null], [self::answer($client)['status'], self::answer($client)['paymentUrl']]);
        self::assertSame(OrderStatus::Paid, self::order($placed['token'], fresh: true)->status());
    }

    public function testADeclinedCheckoutPastItsHoldIsNotAskedForAgain(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::FAILED);
        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);
        $clock->sleep(35 * 60);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token'], server: ['REMOTE_ADDR' => '203.0.113.12']);

        self::assertSame('failed', self::answer($client)['status']);
        self::assertSame(20, self::statusLimiter()->create('203.0.113.12')->consume(0)->getRemainingTokens());
    }

    public function testAnOrderPastItsHoldIsExpiredWithoutPaymentLink(): void
    {
        $clock = self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        $clock->sleep(35 * 60);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);

        self::assertSame(['expired', null], [self::answer($client)['status'], self::answer($client)['paymentUrl']]);
    }

    public function testWhenSumUpIsDownTheStoredStateIsAnswered(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->goDown();

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(['pending', $placed['paymentUrl']], [self::answer($client)['status'], self::answer($client)['paymentUrl']]);
    }

    public function testAnonymousVisitorsFollowTheirOrderWhileTheDoorIsShut(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        $client->getCookieJar()->clear();

        $client->jsonRequest('GET', '/api/shop/fr/orders/'.$placed['token']);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
        self::assertSame([$placed['reference'], 'paid', true], [self::answer($client)['reference'], self::answer($client)['status'], self::answer($client)['test']]);
    }

    public function testAnUnknownTokenIsNotFound(): void
    {
        $client = self::createClient();

        foreach ([str_repeat('A', 43), 'short', str_repeat('A', 44)] as $token) {
            $client->jsonRequest('GET', '/api/shop/fr/orders/'.$token);

            self::assertResponseStatusCodeSame(404, $token);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('problem.not_found', Json::string(self::answer($client), 'code'));
        }
    }

    public function testThePreviewRouteNeedsASession(): void
    {
        self::createClient()->jsonRequest('GET', '/beta/api/shop/fr/orders/'.str_repeat('A', 43));

        self::assertResponseStatusCodeSame(401);
    }

    public function testBeyondTheLimitTheStoredStateIsAnsweredWithoutAskingSumUp(): void
    {
        $client = self::signedInClient();
        $placed = self::placeWithoutRequest();
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::statusLimiter()->create('203.0.113.9')->consume(20);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token'], server: ['REMOTE_ADDR' => '203.0.113.9']);

        self::assertResponseIsSuccessful();
        self::assertSame('pending', self::answer($client)['status']);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token'], server: ['REMOTE_ADDR' => '203.0.113.10']);

        self::assertSame('paid', self::answer($client)['status']);
    }

    public function testTheTwentiethVerificationOfAMinuteIsMade(): void
    {
        $client = self::signedInClient();
        $placed = self::placeWithoutRequest();
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::statusLimiter()->create('203.0.113.9')->consume(19);

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token'], server: ['REMOTE_ADDR' => '203.0.113.9']);

        self::assertSame('paid', self::answer($client)['status']);
    }

    public function testASettledOrderIsNotVerifiedAgain(): void
    {
        $client = self::signedInClient();
        $placed = self::placeWithoutRequest();
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::getContainer()->get(ConfirmPaymentHandler::class)(new ConfirmPayment($placed['paymentId']));

        $client->jsonRequest('GET', '/beta/api/shop/en/orders/'.$placed['token'], server: ['REMOTE_ADDR' => '203.0.113.11']);

        self::assertSame('paid', self::answer($client)['status']);
        self::assertSame(20, self::statusLimiter()->create('203.0.113.11')->consume(0)->getRemainingTokens());
    }

    private static function statusLimiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.order_status');
    }
}
