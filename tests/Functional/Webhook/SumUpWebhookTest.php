<?php

declare(strict_types=1);

namespace App\Tests\Functional\Webhook;

use App\Domain\Sales\OrderStatus;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\FakeSumUp;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\RouterInterface;

final class SumUpWebhookTest extends WebTestCase
{
    use ShopsOnline;

    protected function tearDown(): void
    {
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testAPaidCheckoutPaysItsOrderOnceVerified(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        $client->getCookieJar()->clear();

        self::notify($client, self::event($placed['paymentId']));

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $client->getResponse()->getContent());
        self::assertResponseNotHasHeader('Set-Cookie');
        $order = self::order($placed['token'], fresh: true);
        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertStringStartsWith('TFAKE', (string) $order->transactionCode());
        self::assertSame(1, self::stockOf('STI-MOTH'));
    }

    public function testADeclinedCheckoutFailsItsOrder(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::FAILED);

        self::notify($client, self::event($placed['paymentId']));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(OrderStatus::Failed, self::order($placed['token'], fresh: true)->status());
    }

    public function testTheWebhookCannotPayAnythingByItself(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);

        self::notify($client, (string) json_encode(['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => $placed['paymentId'], 'status' => 'PAID', 'amount' => 12.5, 'transaction_code' => 'TFORGED']));

        self::assertResponseStatusCodeSame(204);
        $order = self::order($placed['token'], fresh: true);
        self::assertSame([OrderStatus::Pending, null], [$order->status(), $order->transactionCode()]);
        self::assertSame(3, self::stockOf('STI-MOTH'));
    }

    /** @return iterable<string, array{string}> */
    public static function bodies(): iterable
    {
        yield 'an unknown checkout' => [self::event('chk-unknown')];
        yield 'not json' => ['id=chk-1'];
        yield 'nothing' => [''];
        yield 'a list' => ['["chk-1"]'];
        yield 'an id that is not text' => ['{"event_type": "CHECKOUT_STATUS_CHANGED", "id": 5}'];
        yield 'an empty id' => ['{"event_type": "CHECKOUT_STATUS_CHANGED", "id": ""}'];
        yield 'an id too long' => [self::event(str_repeat('a', 256))];
        yield 'another event' => ['{"event_type": "PAYOUT_SENT", "id": "chk-1"}'];
    }

    #[DataProvider('bodies')]
    public function testAnythingElseIsAnsweredWithNoContent(string $body): void
    {
        $client = self::createClient();

        self::notify($client, $body);

        self::assertResponseStatusCodeSame(204);
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testWhenSumUpIsDownTheWebhookStillAnswersAndChangesNothing(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::sumUp()->goDown();

        self::notify($client, self::event($placed['paymentId']));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(OrderStatus::Pending, self::order($placed['token'], fresh: true)->status());
    }

    public function testAnOversizedEventIsNotEvenRead(): void
    {
        $client = self::stockedClient();
        $placed = self::placeThroughTheShop($client);
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);

        self::notify($client, (string) json_encode(['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => $placed['paymentId'], 'padding' => str_repeat('a', 4096)]));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(OrderStatus::Pending, self::order($placed['token'], fresh: true)->status());
    }

    public function testBeyondTheLimitTheWebhookDoesNothing(): void
    {
        $client = self::createClient();
        $placed = self::placeWithoutRequest();
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::webhookLimiter()->create('203.0.113.9')->consume(60);

        self::notify($client, self::event($placed['paymentId']), '203.0.113.9');

        self::assertResponseStatusCodeSame(204);
        self::assertSame(OrderStatus::Pending, self::order($placed['token'], fresh: true)->status());

        self::notify($client, self::event($placed['paymentId']), '203.0.113.10');

        self::assertSame(OrderStatus::Paid, self::order($placed['token'], fresh: true)->status());
    }

    public function testTheSixtiethEventOfAMinuteIsHandled(): void
    {
        $client = self::createClient();
        $placed = self::placeWithoutRequest();
        self::sumUp()->settle($placed['paymentId'], FakeSumUp::PAID);
        self::webhookLimiter()->create('203.0.113.9')->consume(59);

        self::notify($client, self::event($placed['paymentId']), '203.0.113.9');

        self::assertSame(OrderStatus::Paid, self::order($placed['token'], fresh: true)->status());
    }

    public function testTheWebhookIsPublicAndStateless(): void
    {
        $route = self::getContainer()->get(RouterInterface::class)->getRouteCollection()->get('webhook_sumup');

        self::assertSame(['/webhooks/sumup', ['POST'], true], [$route?->getPath(), $route?->getMethods(), $route?->getDefault('_stateless')]);
    }

    private static function event(string $id): string
    {
        return (string) json_encode(['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => $id]);
    }

    private static function notify(KernelBrowser $client, string $body, string $address = '198.51.100.7'): void
    {
        $client->request('POST', '/webhooks/sumup', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $address], content: $body);
    }

    private static function webhookLimiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.webhook');
    }
}
