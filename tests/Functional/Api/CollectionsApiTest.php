<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
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
        self::assertSame([['Forest folk', 0, 2, false], ['Night owls', 1, 1, false]], array_map(static fn (mixed $collection): array => [Json::string($collection, 'name'), Json::int($collection, 'position'), Json::int($collection, 'onSale'), Json::at($collection, 'shown')], $collections));
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
        $collection = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([$folk, 'Woodland folk', 'Peuple des bois'], [$collection['id'], $collection['label'], $collection['labelFr']]);
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame('Peuple des bois', Json::string(Json::decode((string) $client->getResponse()->getContent()), 0, 'collection', 'labelFr'));

        $client->jsonRequest('PUT', "/api/admin/collections/{$folk}", ['label' => '', 'labelFr' => '']);

        self::assertResponseIsSuccessful();
        self::assertSame(['', ''], [Json::string(Json::decode((string) $client->getResponse()->getContent()), 'label'), Json::string(Json::decode((string) $client->getResponse()->getContent()), 'labelFr')]);
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

    public function testAnUnknownCollectionIsNotFound(): void
    {
        $client = self::signedInClient();
        self::pushCollections();

        $client->jsonRequest('PUT', '/api/admin/collections/'.new Ulid(), ['label' => '', 'labelFr' => '']);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
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
