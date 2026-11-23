<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Uid\Ulid;

final class CollectionsApiTest extends WebTestCase
{
    use PushesCatalogue;
    use SignsInClient;

    public function testCollectionsAreListedInTheirOrderWithTheNumberOfProductsOnSale(): void
    {
        $client = self::signedInClient();
        self::push([
            self::product(1, 'Prints', 'Fox', 1500, 5, self::collection(1, 'Forest folk')),
            self::product(2, 'Stickers', 'Owl', 300, 0, self::collection(2, 'Night owls')),
            self::product(3, 'Prints', 'Hare', 0, 5, self::collection(1, 'Forest folk')),
            self::product(4, 'Cards', 'Bee', 500, 3, self::collection(1, 'Forest folk')),
            self::product(5, 'Cards', 'Moth', 500, 3, self::collection(1, 'Forest folk')),
            self::product(6, 'Cards', 'Wren', 500, 3),
        ]);
        self::push([
            self::product(1, 'Prints', 'Fox', 1500, 5),
            self::product(2, 'Stickers', 'Owl', 300, 0),
            self::product(3, 'Prints', 'Hare', 0, 5),
            self::product(5, 'Cards', 'Moth', 500, 3),
            self::product(6, 'Cards', 'Wren', 500, 3),
        ]);

        $client->jsonRequest('GET', '/api/admin/collections');

        self::assertResponseIsSuccessful();
        $collections = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([
            ['id' => Json::string($collections, 0, 'id'), 'name' => 'Forest folk', 'label' => '', 'labelFr' => '', 'shown' => false, 'position' => 0, 'onSale' => 2],
            ['id' => Json::string($collections, 1, 'id'), 'name' => 'Night owls', 'label' => '', 'labelFr' => '', 'shown' => false, 'position' => 1, 'onSale' => 1],
        ], $collections);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', Json::string($collections, 0, 'id'));
    }

    public function testTheListRunsTheSameQueriesWhateverTheNumberOfCollections(): void
    {
        $client = self::signedInClient();
        self::push([self::product(1, 'Prints', 'Fox', 1500, 5, self::collection(1, 'Forest folk'))]);
        $few = self::listQueries($client);

        self::push(array_map(static fn (int $number): PushedProduct => self::product($number, 'Prints', 'Fern'.$number, 900, 5, self::collection($number, 'Ferns '.$number)), range(1, 6)));

        self::assertSame($few, self::listQueries($client));
    }

    public function testACollectionIsLabelledInBothLanguages(): void
    {
        $client = self::signedInClient();
        [$folk] = self::pushCollections();

        $client->jsonRequest('PUT', "/api/admin/collections/{$folk}", ['label' => ' Woodland folk ', 'labelFr' => 'Peuple des bois']);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $folk, 'name' => 'Forest folk', 'label' => 'Woodland folk', 'labelFr' => 'Peuple des bois', 'shown' => false, 'position' => 0, 'onSale' => 1], Json::decode((string) $client->getResponse()->getContent()));
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame('Peuple des bois', Json::string(Json::decode((string) $client->getResponse()->getContent()), 0, 'collection', 'labelFr'));

        $client->jsonRequest('PUT', "/api/admin/collections/{$folk}", ['label' => '', 'labelFr' => '']);

        self::assertResponseIsSuccessful();
        self::assertSame(['', ''], [Json::string(Json::decode((string) $client->getResponse()->getContent()), 'label'), Json::string(Json::decode((string) $client->getResponse()->getContent()), 'labelFr')]);
    }

    public function testALabelHoldsSixtyCharactersPerLanguage(): void
    {
        $client = self::signedInClient();
        [$folk] = self::pushCollections();

        $client->jsonRequest('PUT', "/api/admin/collections/{$folk}", ['label' => str_repeat('a', 60), 'labelFr' => str_repeat('é', 60)]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PUT', "/api/admin/collections/{$folk}", ['label' => 'Folk', 'labelFr' => str_repeat('é', 61)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(422, Json::int(Json::decode((string) $client->getResponse()->getContent()), 'status'));
        $client->jsonRequest('GET', '/api/admin/collections');
        self::assertSame(str_repeat('é', 60), Json::string(Json::decode((string) $client->getResponse()->getContent()), 0, 'labelFr'));
    }

    public function testACollectionIsShownAndHidden(): void
    {
        $client = self::signedInClient();
        [$folk, $night] = self::pushCollections();

        $client->jsonRequest('PUT', "/api/admin/collections/{$folk}/visibility");

        self::assertResponseIsSuccessful();
        self::assertTrue(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'shown'));
        $client->jsonRequest('GET', '/api/admin/collections');
        self::assertSame([true, false], array_column(Json::decode((string) $client->getResponse()->getContent()), 'shown'));

        $client->jsonRequest('DELETE', "/api/admin/collections/{$folk}/visibility");

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $folk, 'shown' => false], array_intersect_key(Json::decode((string) $client->getResponse()->getContent()), ['id' => true, 'shown' => true]));
        self::assertNotSame($folk, $night);
    }

    public function testCollectionsAreReordered(): void
    {
        $client = self::signedInClient();
        [$folk, $night] = self::pushCollections();

        $client->jsonRequest('PUT', '/api/admin/collections/order', ['ids' => [$night, $folk]]);

        self::assertResponseIsSuccessful();
        self::assertSame([[$night, 0], [$folk, 1]], array_map(static fn (mixed $collection): array => [Json::string($collection, 'id'), Json::int($collection, 'position')], Json::decode((string) $client->getResponse()->getContent())));

        $client->jsonRequest('PUT', '/api/admin/collections/order', ['ids' => [$night]]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('PUT', '/api/admin/collections/order', ['ids' => [$night, (string) new Ulid()]]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('GET', '/api/admin/collections');
        self::assertSame([$night, $folk], array_column(Json::decode((string) $client->getResponse()->getContent()), 'id'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routesOfOneCollection(): iterable
    {
        yield 'label' => ['PUT', ''];
        yield 'show' => ['PUT', '/visibility'];
        yield 'hide' => ['DELETE', '/visibility'];
    }

    #[DataProvider('routesOfOneCollection')]
    public function testAnUnknownCollectionIsNotFound(string $method, string $suffix): void
    {
        $client = self::signedInClient();
        self::pushCollections();

        $client->jsonRequest($method, '/api/admin/collections/'.new Ulid().$suffix, ['label' => '', 'labelFr' => '']);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        $collection = '/api/admin/collections/'.new Ulid();

        yield 'list' => ['GET', '/api/admin/collections'];
        yield 'label' => ['PUT', $collection];
        yield 'show' => ['PUT', $collection.'/visibility'];
        yield 'hide' => ['DELETE', $collection.'/visibility'];
        yield 'reorder' => ['PUT', '/api/admin/collections/order'];
    }

    #[DataProvider('routes')]
    public function testEveryRouteNeedsASignedInEditor(string $method, string $url): void
    {
        $client = self::createClient();

        $client->jsonRequest($method, $url);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return list<string>
     */
    private static function pushCollections(): array
    {
        self::push([
            self::product(1, 'Prints', 'Fox', 1500, 5, self::collection(1, 'Forest folk')),
            self::product(2, 'Stickers', 'Owl', 300, 4, self::collection(2, 'Night owls')),
        ]);
        $client = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->jsonRequest('GET', '/api/admin/collections');

        return array_values(array_map(static fn (mixed $collection): string => Json::string($collection, 'id'), Json::decode((string) $client->getResponse()->getContent())));
    }

    private static function listQueries(KernelBrowser $client): int
    {
        $client->jsonRequest('GET', '/api/admin/collections');
        $client->enableProfiler();
        $client->jsonRequest('GET', '/api/admin/collections');
        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
