<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\RouterInterface;

final class ShopQuoteApiTest extends WebTestCase
{
    use PushesCatalogue;
    use SignsInClient;

    private const string URL = '/beta/api/shop/fr/quote';

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testTheQuoteAnswersTheLinesTheIssuesAndTheTotals(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => 'Fox print', 'titleFr' => 'Affiche renard']);
        self::photo($client, $ids['prints-fox']);
        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', ['price' => 650, 'freeFrom' => 6000, 'enabled' => true]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', self::URL, ['lines' => [['sku' => 'PRI-FOX-A4', 'quantity' => 12], ['sku' => 'STI-MOTH', 'quantity' => 1], ['sku' => 'NOPE', 'quantity' => 1]], 'country' => 'FR'], ['REMOTE_ADDR' => '203.0.113.20']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseNotHasHeader('Set-Cookie');
        $quote = Json::decode((string) $client->getResponse()->getContent());
        $image = Json::array($quote, 'lines', 0, 'image');
        self::assertSame(['src', 'srcset', 'width', 'height', 'alt'], array_keys($image));
        self::assertStringStartsWith('/media/artworks/', Json::string($image, 'src'));
        self::assertSame('', $image['alt']);
        self::assertSame([
            'lines' => [
                ['sku' => 'PRI-FOX-A4', 'title' => 'Affiche renard', 'variant' => 'A4', 'unitPrice' => 1500, 'quantity' => 10, 'lineTotal' => 15000, 'image' => $image, 'url' => '/beta/fr/shop/prints-fox', 'available' => 10],
                ['sku' => 'STI-MOTH', 'title' => 'Stickers Moth', 'variant' => null, 'unitPrice' => 300, 'quantity' => 1, 'lineTotal' => 300, 'image' => null, 'url' => '/beta/fr/shop/stickers-moth', 'available' => 4],
            ],
            'issues' => [
                ['type' => 'reduced', 'sku' => 'PRI-FOX-A4', 'title' => 'Affiche renard'],
                ['type' => 'removed', 'sku' => 'NOPE', 'title' => null],
            ],
            'goods' => 15300,
            'shipping' => ['zone' => 'france', 'price' => 0, 'freeFrom' => 6000, 'free' => true],
            'deliverable' => true,
            'total' => 15300,
            'units' => 11,
        ], $quote);
    }

    public function testWithoutACountryTheQuoteHasNoShipping(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);

        $client->jsonRequest('POST', '/beta/api/shop/en/quote', ['lines' => [['sku' => 'PRI-FOX-A4', 'quantity' => 1]]]);

        self::assertResponseIsSuccessful();
        $quote = Json::decode((string) $client->getResponse()->getContent());
        self::assertNull($quote['shipping']);
        self::assertFalse($quote['deliverable']);
        self::assertSame(['/beta/en/shop/prints-fox', 'Prints Fox', 1500], [Json::string($quote, 'lines', 0, 'url'), Json::string($quote, 'lines', 0, 'title'), Json::int($quote, 'total')]);
    }

    public function testAnEmptyCartIsQuotedAtZero(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, ['lines' => [], 'country' => 'FR']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['lines' => [], 'issues' => [], 'goods' => 0, 'shipping' => ['zone' => 'france', 'price' => 0, 'freeFrom' => null, 'free' => false], 'deliverable' => false, 'total' => 0, 'units' => 0],
            Json::decode((string) $client->getResponse()->getContent()),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'no lines' => ['{"country": "FR"}'];
        yield 'lines not a list' => ['{"lines": "PRI-FOX-A4"}'];
        yield 'a line without a sku' => ['{"lines": [{"quantity": 1}]}'];
        yield 'an empty sku' => ['{"lines": [{"sku": "", "quantity": 1}]}'];
        yield 'a sku too long' => ['{"lines": [{"sku": "'.str_repeat('a', 321).'", "quantity": 1}]}'];
        yield 'a quantity that is not a number' => ['{"lines": [{"sku": "PRI-FOX-A4", "quantity": "two"}]}'];
        yield 'a country that is not text' => ['{"lines": [], "country": 33}'];
    }

    #[DataProvider('malformedBodies')]
    public function testAMalformedBodyIsRefused(string $body): void
    {
        $client = self::signedInClient();

        $client->request('POST', self::URL, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $body);

        self::assertResponseStatusCodeSame(422);
    }

    public function testABodyThatIsNotJsonIsABadRequest(): void
    {
        $client = self::signedInClient();

        $client->request('POST', self::URL, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: 'lines=');

        self::assertResponseStatusCodeSame(400);
    }

    public function testAnUnknownCountryIsRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, ['lines' => [], 'country' => 'XX']);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('sales.unknown_country', Json::string(self::body($client), 'code'));
    }

    public function testMoreThanThirtyLinesAreRefused(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, ['lines' => array_map(static fn (int $number): array => ['sku' => 'SKU-'.$number, 'quantity' => 1], range(1, 31)), 'country' => null]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('sales.cart_too_large', Json::string(self::body($client), 'code'));
    }

    public function testTheShutDoorHidesThePublicRouteWithoutACookie(): void
    {
        $client = self::createClient();

        foreach (['/api/shop/fr/quote', '/api/shop/en/quote'] as $url) {
            $client->jsonRequest('POST', $url, ['lines' => [], 'country' => 'FR']);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
            self::assertSame('problem.not_found', Json::string(self::body($client), 'code'));
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }

        $client->jsonRequest('GET', '/api/shop/fr/quote');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePreviewRouteNeedsASession(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', self::URL, ['lines' => [], 'country' => 'FR']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheSixtiethQuoteOfAMinuteIsAnswered(): void
    {
        $client = self::signedInClient();
        self::quoteLimiter()->create('203.0.113.9')->consume(59);

        $client->jsonRequest('POST', self::URL, ['lines' => [], 'country' => 'FR'], ['REMOTE_ADDR' => '203.0.113.9']);

        self::assertResponseIsSuccessful();
        self::assertSame(0, self::quoteLimiter()->create('203.0.113.9')->consume(0)->getRemainingTokens());
        self::assertSame(60, self::quoteLimiter()->create('203.0.113.10')->consume(0)->getRemainingTokens());
    }

    public function testTheSixtyFirstQuoteOfAMinuteIsRefused(): void
    {
        $client = self::signedInClient();
        self::quoteLimiter()->create('203.0.113.9')->consume(60);

        $client->jsonRequest('POST', self::URL, ['lines' => [], 'country' => 'FR'], ['REMOTE_ADDR' => '203.0.113.9']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('problem.too_many_requests', Json::string(self::body($client), 'code'));
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(61, $retryAfter);
    }

    public function testTheQuoteStartsNoSession(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', self::URL, ['lines' => [], 'country' => 'FR']);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
        self::assertTrue(self::getContainer()->get(RouterInterface::class)->getRouteCollection()->get('api_shop_quote')?->getDefault('_stateless'));
    }

    private static function quoteLimiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.quote');
    }

    /** @return array<mixed> */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
