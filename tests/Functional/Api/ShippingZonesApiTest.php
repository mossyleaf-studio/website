<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShippingZonesApiTest extends WebTestCase
{
    use SignsInClient;

    private const array OFF = [
        ['code' => 'france', 'price' => 0, 'freeFrom' => null, 'enabled' => false],
        ['code' => 'eu', 'price' => 0, 'freeFrom' => null, 'enabled' => false],
        ['code' => 'world', 'price' => 0, 'freeFrom' => null, 'enabled' => false],
    ];

    public function testTheMigrationSeedsTheThreeZonesOffAndFree(): void
    {
        self::createClient();

        self::assertSame(
            [['code' => 'eu', 'price' => 0, 'free_from' => null, 'enabled' => false], ['code' => 'france', 'price' => 0, 'free_from' => null, 'enabled' => false], ['code' => 'world', 'price' => 0, 'free_from' => null, 'enabled' => false]],
            self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT code, price, free_from, enabled FROM shipping_zone ORDER BY code'),
        );
    }

    public function testTheZonesAreListedFranceFirst(): void
    {
        $client = self::signedInClient();

        self::assertSame(self::OFF, self::zones($client));
    }

    public function testAZoneIsEditedAndLiveAtOnce(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/shipping-zones/eu', ['price' => 1250, 'freeFrom' => 8000, 'enabled' => true]);

        self::assertResponseIsSuccessful();
        self::assertSame(['code' => 'eu', 'price' => 1250, 'freeFrom' => 8000, 'enabled' => true], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame([self::OFF[0], ['code' => 'eu', 'price' => 1250, 'freeFrom' => 8000, 'enabled' => true], self::OFF[2]], self::zones($client));
    }

    public function testNoThresholdMeansNoFreeShipping(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', ['price' => 650, 'freeFrom' => 6000, 'enabled' => true]);

        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', ['price' => 650, 'freeFrom' => null, 'enabled' => true]);

        self::assertResponseIsSuccessful();
        self::assertSame(['code' => 'france', 'price' => 650, 'freeFrom' => null, 'enabled' => true], Json::array(self::zones($client), 0));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refusedFields(): iterable
    {
        yield 'negative price' => [['price' => -1, 'freeFrom' => null, 'enabled' => true], 'price'];
        yield 'negative threshold' => [['price' => 0, 'freeFrom' => -100, 'enabled' => true], 'freeFrom'];
        yield 'price above the maximum' => [['price' => 1_000_001, 'freeFrom' => null, 'enabled' => true], 'price'];
        yield 'threshold above the maximum' => [['price' => 0, 'freeFrom' => 3_000_000_000, 'enabled' => true], 'freeFrom'];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('refusedFields')]
    public function testAnAmountOutOfBoundsIsRefused(array $payload, string $field): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/shipping-zones/france', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([$field], array_column(Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'), 'propertyPath'));
        self::assertSame(self::OFF, self::zones($client));
    }

    public function testAnUnknownZoneIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/shipping-zones/mars', ['price' => 0, 'freeFrom' => null, 'enabled' => true]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testEveryRouteNeedsAnAdmin(): void
    {
        $client = self::createClient();

        foreach ([['GET', '/api/admin/shipping-zones'], ['PUT', '/api/admin/shipping-zones/france']] as [$method, $url]) {
            $client->jsonRequest($method, $url, ['price' => 0, 'freeFrom' => null, 'enabled' => true]);

            self::assertResponseStatusCodeSame(401, $method);
        }
    }

    public function testTheSettingsPageIsPreloadedWithTheZones(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/admin/settings');

        self::assertResponseIsSuccessful();
        self::assertSame(self::OFF, Json::array(Json::decode($crawler->filter('#app-preload')->text()), '/api/admin/shipping-zones'));
    }

    /** @return list<mixed> */
    private static function zones(KernelBrowser $client): array
    {
        $client->jsonRequest('GET', '/api/admin/shipping-zones');
        self::assertResponseIsSuccessful();

        return array_values(Json::decode((string) $client->getResponse()->getContent()));
    }
}
