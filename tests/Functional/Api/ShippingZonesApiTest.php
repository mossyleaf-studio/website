<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
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

    public function testTheZonesAreListedFranceFirstOffAndFree(): void
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

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refusedFields(): iterable
    {
        yield 'negative price' => [['price' => -1, 'freeFrom' => null, 'enabled' => true], 'price'];
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
