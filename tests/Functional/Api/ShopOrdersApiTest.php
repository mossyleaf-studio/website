<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Locale;
use App\Tests\Functional\Sales\ShopsOnline;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class ShopOrdersApiTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use ShopsOnline;

    private const string URL = '/beta/api/shop/fr/orders';
    private const array SETTINGS = ['SUMUP_API_KEY', 'SUMUP_MERCHANT_CODE'];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::SETTINGS as $name) {
            $this->saved[$name] = $_ENV[$name] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            if (\is_string($value)) {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }
        self::forgetCheckouts();
        parent::tearDown();
    }

    public function testAnOrderIsHeldAndItsCheckoutOpenedAtSumUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        $client = self::stockedClient();

        $client->jsonRequest('POST', self::URL, self::form(), ['REMOTE_ADDR' => '203.0.113.30']);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseNotHasHeader('Set-Cookie');
        $placed = self::answer($client);
        $reference = Json::string($placed, 'reference');
        self::assertMatchesRegularExpression('/^ML-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $reference);
        self::assertMatchesRegularExpression('#^/beta/fr/order/[A-Za-z0-9_-]{43}$#', Json::string($placed, 'statusUrl'));
        $token = substr(Json::string($placed, 'statusUrl'), -43);

        $order = self::order($token, fresh: true);
        self::assertSame('/_fake/sumup/'.$order->paymentId(), $placed['paymentUrl']);
        self::assertSame([$reference, OrderStatus::Pending, true, Locale::Fr, 'FR', Zone::France, 600, 650], [$order->reference(), $order->status(), $order->isTest(), $order->locale(), $order->country(), $order->zone(), $order->goods(), $order->shipping()]);
        self::assertSame(self::CUSTOMER, [$order->email(), $order->name(), $order->phone(), $order->line1(), $order->line2(), $order->postalCode(), $order->city()]);
        self::assertNull($order->region());
        self::assertSame([['STI-MOTH', 'Stickers Moth', 2, 300]], array_map(static fn ($line): array => [$line->sku(), $line->title(), $line->quantity(), $line->unitPrice()], $order->lines()));
        self::assertSame(3, self::stockOf('STI-MOTH'));
        self::assertSame(1, self::available('stickers-moth', 'STI-MOTH', true));

        $checkout = self::sumUp()->checkout((string) $order->paymentId()) ?? throw new \LogicException('No checkout at SumUp');
        self::assertSame([
            'checkout_reference' => $reference,
            'amount' => 12.5,
            'currency' => 'EUR',
            'merchant_code' => 'MTEST0001',
            'description' => 'TEST mossyleaf.studio order '.$reference,
            'return_url' => 'http://localhost/webhooks/sumup',
            'redirect_url' => 'http://localhost/beta/fr/order/'.$token,
            'valid_until' => new \DateTimeImmutable('2026-11-20 10:30:00')->format(\DATE_ATOM),
            'hosted_checkout' => ['enabled' => true],
        ], array_intersect_key($checkout, array_flip(['checkout_reference', 'amount', 'currency', 'merchant_code', 'description', 'return_url', 'redirect_url', 'valid_until', 'hosted_checkout'])));
        self::assertSame('PENDING', $checkout['status']);
    }

    public function testTheClientCannotChooseTheStatusTheTestFlagTheAmountsNorTheReference(): void
    {
        $client = self::stockedClient();

        $client->jsonRequest('POST', self::URL, self::form(['status' => 'paid', 'test' => false, 'goods' => 1, 'shipping' => 0, 'total' => 1, 'reference' => 'ML-AAAA-AAAA', 'token' => str_repeat('a', 43), 'paymentId' => 'mine', 'paymentUrl' => 'https://evil.test', 'zone' => 'france', 'lines' => [['sku' => 'STI-MOTH', 'quantity' => 2, 'unitPrice' => 1]]]));

        self::assertResponseStatusCodeSame(201);
        $placed = self::answer($client);
        self::assertNotSame('ML-AAAA-AAAA', $placed['reference']);
        $order = self::order(substr(Json::string($placed, 'statusUrl'), -43));
        self::assertNotSame(str_repeat('a', 43), $order->token());
        self::assertSame([OrderStatus::Pending, true, 600, 650, 300], [$order->status(), $order->isTest(), $order->goods(), $order->shipping(), $order->lines()[0]->unitPrice()]);
        self::assertNotSame('mine', $order->paymentId());
        self::assertStringStartsWith('/_fake/sumup/', (string) $order->paymentUrl());
    }

    public function testAnInvalidOrderIsRefusedByItsPropertyPathAndNothingIsPlaced(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, self::form(['terms' => null]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['terms'], array_values(array_unique(array_map(static fn (mixed $violation): string => Json::string($violation, 'propertyPath'), Json::array(self::answer($client), 'violations')))));
        self::assertSame(0, self::orderCount());
        self::assertSame(0, self::sumUp()->count());
    }

    public function testATotalThatChangedOrACountryNotDeliveredIsAConflict(): void
    {
        $client = self::stockedClient();

        foreach ([['expectedTotal' => 1249], ['country' => 'DE']] as $changes) {
            $client->jsonRequest('POST', self::URL, self::form($changes));

            self::assertResponseStatusCodeSame(409);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('sales.cart_outdated', Json::string(self::answer($client), 'code'));
            self::assertSame(0, self::orderCount());
            self::assertSame(0, self::sumUp()->count());
        }
    }

    public function testWhenSumUpFailsNothingIsChargedAndTheHoldEnds(): void
    {
        $client = self::stockedClient();
        self::sumUp()->goDown();

        $client->jsonRequest('POST', self::URL, self::form());

        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $problem = self::answer($client);
        self::assertSame('sales.payment_unavailable', Json::string($problem, 'code'));
        self::assertStringNotContainsString('SumUp says', (string) $client->getResponse()->getContent());
        self::assertSame(1, self::orderCount());
        self::assertSame(3, self::available('stickers-moth', 'STI-MOTH', true));
    }

    public function testWithoutTheSumUpSettingsTheRouteIsHidden(): void
    {
        foreach (self::SETTINGS as $name) {
            $_ENV[$name] = '';
            $client = self::signedInClient();

            $client->jsonRequest('POST', self::URL, self::form());

            self::assertResponseStatusCodeSame(404, $name);
            self::assertSame('problem.not_found', Json::string(self::answer($client), 'code'));
            $_ENV[$name] = $this->saved[$name];
            self::ensureKernelShutdown();
        }
    }

    public function testTheEleventhOrderOfTenMinutesIsRefused(): void
    {
        $client = self::signedInClient();
        self::checkoutLimiter()->create('203.0.113.9')->consume(10);

        $client->jsonRequest('POST', self::URL, self::form(), ['REMOTE_ADDR' => '203.0.113.9']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('problem.too_many_requests', Json::string(self::answer($client), 'code'));
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(601, $retryAfter);
        self::assertSame(0, self::orderCount());
    }

    private static function checkoutLimiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.checkout');
    }
}
