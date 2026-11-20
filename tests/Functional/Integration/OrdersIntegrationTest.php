<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrdersIntegrationTest extends WebTestCase
{
    private const string TOKEN = 'test-mossytrunk-token-0123456789abcdef';

    protected function tearDown(): void
    {
        $_ENV['MOSSYTRUNK_TOKEN'] = self::TOKEN;

        parent::tearDown();
    }

    public function testNoOrderIsListedWhileTheShopHasSoldNothing(): void
    {
        $client = self::createClient();

        $client->request('GET', '/integrations/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseIsSuccessful();
        self::assertSame(['orders' => []], Json::decode((string) $client->getResponse()->getContent()));
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
    }

    public function testTheOrdersAskForTheToken(): void
    {
        $client = self::createClient();

        $client->request('GET', '/integrations/orders');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
    }

    public function testTheOrdersAreNotFoundWhileTheIntegrationIsOff(): void
    {
        $_ENV['MOSSYTRUNK_TOKEN'] = '';
        $client = self::createClient();

        $client->request('GET', '/integrations/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheOrdersAreOnlyRead(): void
    {
        $client = self::createClient();

        $client->request('POST', '/integrations/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseStatusCodeSame(405);
    }
}
