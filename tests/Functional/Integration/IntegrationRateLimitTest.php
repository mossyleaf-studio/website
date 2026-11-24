<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class IntegrationRateLimitTest extends WebTestCase
{
    private const string TOKEN = 'test-mossytrunk-token-0123456789abcdef';
    private const string ADDRESS = '203.0.113.9';
    private const array PRODUCT = ['id' => '01JD3K7Q0000000000000000F0', 'reference' => 'PRT-FOX', 'name' => 'Fox', 'category' => 'Prints', 'displayName' => 'Prints Fox', 'price' => 1500, 'variants' => [['name' => 'A4', 'sku' => 'PRT-FOX-A4', 'stock' => 12]]];

    public function testAFailedTokenCountsAgainstTheAddressAndTheRightTokenCostsNothing(): void
    {
        $client = self::createClient();

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::PRODUCT]], ['REMOTE_ADDR' => self::ADDRESS, 'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseIsSuccessful();
        self::assertSame(10, self::remaining(self::ADDRESS));

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::PRODUCT]], ['REMOTE_ADDR' => self::ADDRESS, 'HTTP_AUTHORIZATION' => 'Bearer '.str_repeat('a', 38)]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(9, self::remaining(self::ADDRESS));
    }

    public function testTheRightTokenPassesAfterNineFailures(): void
    {
        $client = self::createClient();
        self::limiter()->create(self::ADDRESS)->consume(9);

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::PRODUCT]], ['REMOTE_ADDR' => self::ADDRESS, 'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseIsSuccessful();
    }

    public function testTheTenthFailureIsStillAnsweredUnauthorized(): void
    {
        $client = self::createClient();
        self::limiter()->create(self::ADDRESS)->consume(9);

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::PRODUCT]], ['REMOTE_ADDR' => self::ADDRESS, 'HTTP_AUTHORIZATION' => 'Bearer wrong']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(0, self::remaining(self::ADDRESS));
    }

    public function testTheEleventhAttemptOfTheMinuteIsRefusedBeforeTheTokenIsCompared(): void
    {
        $client = self::createClient();
        self::limiter()->create(self::ADDRESS)->consume(10);

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::PRODUCT]], ['REMOTE_ADDR' => self::ADDRESS, 'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseStatusCodeSame(429);
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(61, $retryAfter);
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testTheAddressBehindTheTrustedProxyIsCountedNotTheProxy(): void
    {
        $client = self::createClient();
        Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_FOR);

        $client->jsonRequest('PUT', '/integrations/catalogue', ['products' => [self::PRODUCT]], ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => self::ADDRESS, 'HTTP_AUTHORIZATION' => 'Bearer wrong']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(9, self::remaining(self::ADDRESS));
        self::assertSame(10, self::remaining('10.0.0.2'));
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        parent::tearDown();
    }

    private static function remaining(string $address): int
    {
        return self::limiter()->create($address)->consume(0)->getRemainingTokens();
    }

    private static function limiter(): RateLimiterFactoryInterface
    {
        return self::getContainer()->get('limiter.integration_authentication');
    }
}
