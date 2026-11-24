<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

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
        self::assertStringStartsWith('/media/artworks/', Json::string($image, 'src'));
        self::assertSame(
            [['PRI-FOX-A4', 'Affiche renard', 'A4', 1500, 10, 15000, '/beta/fr/shop/prints-fox', 10], ['STI-MOTH', 'Stickers Moth', null, 300, 1, 300, '/beta/fr/shop/stickers-moth', 4]],
            array_map(static fn (mixed $line): array => [Json::string($line, 'sku'), Json::string($line, 'title'), Json::at($line, 'variant'), Json::int($line, 'unitPrice'), Json::int($line, 'quantity'), Json::int($line, 'lineTotal'), Json::string($line, 'url'), Json::int($line, 'available')], Json::array($quote, 'lines')),
        );
        self::assertNull(Json::at($quote, 'lines', 1, 'image'));
        self::assertSame([['reduced', 'PRI-FOX-A4'], ['removed', 'NOPE']], array_map(static fn (mixed $issue): array => [Json::string($issue, 'type'), Json::string($issue, 'sku')], Json::array($quote, 'issues')));
        self::assertSame([15300, 15300, 11, true], [$quote['goods'], $quote['total'], $quote['units'], $quote['deliverable']]);
        self::assertSame(['france', 0, 6000, true], [Json::string($quote, 'shipping', 'zone'), Json::int($quote, 'shipping', 'price'), Json::int($quote, 'shipping', 'freeFrom'), Json::at($quote, 'shipping', 'free')]);
    }

    public function testAMalformedBodyIsRefused(): void
    {
        $client = self::signedInClient();

        $client->request('POST', self::URL, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"lines": [{"sku": "", "quantity": 1}]}');

        self::assertResponseStatusCodeSame(422);
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
