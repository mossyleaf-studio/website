<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\ReplaceCatalogue\PushedCollection;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Uid\Ulid;

final class ProductsApiTest extends WebTestCase
{
    use SignsInClient;

    private const string FOX = '01JD3K7Q0000000000000000F0';
    private const string OWL = '01JD3K7Q0000000000000000F1';
    private const string FOLK = '01JD3K7Q0000000000000000C0';
    private const string NIGHT = '01JD3K7Q0000000000000000C1';

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testTheListCarriesWhatMossytrunkOwnsAndWhatTheWebsiteOwns(): void
    {
        $client = self::signedInClient();
        self::push([self::fox(), self::owl()]);

        $client->jsonRequest('GET', '/api/admin/products');

        self::assertResponseIsSuccessful();
        $products = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['PRT-FOX', 'STK-OWL'], array_column($products, 'reference'));
        $fox = Json::array($products, 0);
        self::assertSame(['Prints Fox', 'prints-fox', 1500, false, false, false, null], [$fox['displayName'], $fox['slug'], $fox['price'], $fox['retired'], $fox['visible'], $fox['picked'], $fox['collection']]);
        self::assertSame(['Prints', 'PRT-FOX'], [Json::string($fox, 'category', 'name'), $fox['reference']]);
        self::assertSame([['PRT-FOX-A4', 12], ['PRT-FOX-A5', 0]], array_map(static fn (mixed $variant): array => [Json::string($variant, 'sku'), Json::int($variant, 'stock')], Json::array($fox, 'variants')));
        self::assertSame(['en' => '/beta/en/shop/prints-fox', 'fr' => '/beta/fr/shop/prints-fox'], $fox['preview']);
    }

    public function testAProductThatCanBeSoldCarriesTheAddressOfItsPreviewInEachLanguage(): void
    {
        $client = self::signedInClient();
        [$fox, $owl] = self::push([self::fox(), self::owl()]);

        $client->jsonRequest('PUT', "/api/admin/products/{$fox}", self::description(['slug' => 'fox-print']));

        $renamed = ['en' => '/beta/en/shop/fox-print', 'fr' => '/beta/fr/shop/fox-print'];
        self::assertSame($renamed, Json::array(Json::decode((string) $client->getResponse()->getContent()), 'preview'));

        $client->request('GET', '/beta/fr/shop/fox-print');

        self::assertResponseIsSuccessful();

        $client->jsonRequest('PUT', "/api/admin/products/{$fox}/visibility");

        self::assertTrue(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'visible'));
        self::assertSame($renamed, Json::array(Json::decode((string) $client->getResponse()->getContent()), 'preview'));

        $client->jsonRequest('DELETE', "/api/admin/products/{$fox}/visibility");

        self::assertFalse(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'visible'));
        self::assertSame($renamed, Json::array(Json::decode((string) $client->getResponse()->getContent()), 'preview'));

        $client->jsonRequest('PUT', '/api/admin/products/order', ['ids' => [$owl, $fox]]);

        $reordered = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['/beta/en/shop/stickers-owl', '/beta/en/shop/fox-print'], [Json::string($reordered, 0, 'preview', 'en'), Json::string($reordered, 1, 'preview', 'en')]);
    }

    public function testAProductThatCannotBeSoldHasNoPreview(): void
    {
        $client = self::signedInClient();
        self::push([self::fox(), new PushedProduct(new Ulid(self::OWL), 'STK-OWL', 'Owl', 'Stickers', 'Stickers Owl', 0, [new PushedVariant(null, 'STK-OWL', 4)])]);

        $client->jsonRequest('GET', '/api/admin/products');

        $products = Json::decode((string) $client->getResponse()->getContent());
        self::assertNull(Json::at($products, 1, 'preview'));

        self::push([self::owl()]);
        $client->jsonRequest('GET', '/api/admin/products');

        $products = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([true, null], [Json::at($products, 0, 'retired'), Json::at($products, 0, 'preview')]);
    }

    public function testAProductIsDescribedInBothLanguagesAndGetsANewSlug(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        $client->jsonRequest('PUT', "/api/admin/products/{$id}", self::description(['slug' => 'fox-print']));

        self::assertResponseIsSuccessful();
        $fox = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['Fox print', 'Affiche renard', 'A fox among ferns.', 'Un renard dans les fougères.', 'fox-print'], [$fox['title'], $fox['titleFr'], $fox['description'], $fox['descriptionFr'], $fox['slug']]);
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame('Affiche renard', Json::string(Json::decode((string) $client->getResponse()->getContent()), 0, 'titleFr'));
    }

    public function testASlugTakenByAnotherProductIsRefusedAndItsOwnIsKept(): void
    {
        $client = self::signedInClient();
        [$fox] = self::push([self::fox(), self::owl()]);

        $client->jsonRequest('PUT', "/api/admin/products/{$fox}", self::description(['slug' => 'stickers-owl']));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('stickers-owl', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));

        $client->jsonRequest('PUT', "/api/admin/products/{$fox}", self::description(['slug' => 'prints-fox']));
        self::assertResponseIsSuccessful();
    }

    public function testAProductIsPickedAndUnpickedWithoutChangingItsVisibility(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        foreach ([1, 2] as $time) {
            $client->jsonRequest('PUT', "/api/admin/products/{$id}/pick");
            self::assertResponseIsSuccessful((string) $time);
            $product = Json::decode((string) $client->getResponse()->getContent());
            self::assertSame([$id, true, false], [Json::string($product, 'id'), Json::at($product, 'picked'), Json::at($product, 'visible')]);
            self::assertSame(['en' => '/beta/en/shop/prints-fox', 'fr' => '/beta/fr/shop/prints-fox'], Json::array($product, 'preview'));
        }

        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame([true], array_column(Json::decode((string) $client->getResponse()->getContent()), 'picked'));

        foreach ([1, 2] as $time) {
            $client->jsonRequest('DELETE', "/api/admin/products/{$id}/pick");
            self::assertResponseIsSuccessful((string) $time);
            self::assertFalse(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'picked'));
        }
    }

    public function testPickingAnUnknownProductIsNotFound(): void
    {
        $client = self::signedInClient();

        foreach (['PUT', 'DELETE'] as $method) {
            $client->jsonRequest($method, '/api/admin/products/'.new Ulid().'/pick');

            self::assertResponseStatusCodeSame(404, $method);
        }
    }

    public function testAnUnknownProductIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/admin/products/'.new Ulid().'/visibility');

        self::assertResponseStatusCodeSame(404);
    }

    public function testProductsAreReordered(): void
    {
        $client = self::signedInClient();
        [$fox, $owl] = self::push([self::fox(), self::owl()]);

        $client->jsonRequest('PUT', '/api/admin/products/order', ['ids' => [$owl, $fox]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$owl, $fox], array_column(Json::decode((string) $client->getResponse()->getContent()), 'id'));
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame([[$owl, 0], [$fox, 1]], array_map(static fn (mixed $product): array => [Json::string($product, 'id'), Json::int($product, 'position')], Json::decode((string) $client->getResponse()->getContent())));
    }

    public function testAPhotoUploadMakesTheFullImageAndA600PixelThumb(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        $photo = Json::array(self::upload($client, $id), 'photos', 0);

        self::assertSame([1600, 1200, 600, 450], [$photo['width'], $photo['height'], $photo['thumbWidth'], $photo['thumbHeight']]);
        foreach ([Json::string($photo, 'url'), Json::string($photo, 'thumbUrl')] as $url) {
            self::assertMatchesRegularExpression('#^/media/artworks/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', $url);
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'image/webp');
        }
        self::assertNotSame($photo['url'], $photo['thumbUrl']);
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame([$photo['id']], array_column(Json::array(Json::decode((string) $client->getResponse()->getContent()), 0, 'photos'), 'id'));
    }

    public function testSomethingThatIsNotAnImageIsRefused(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        $client->request('POST', "/api/admin/products/{$id}/photos", files: ['image' => self::file('not-an-image.png')], server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAPhotoIsDeletedWithItsFiles(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];
        $first = Json::array(self::upload($client, $id), 'photos', 0);
        self::upload($client, $id);

        $client->jsonRequest('DELETE', "/api/admin/products/{$id}/photos/".Json::string($first, 'id'));

        self::assertResponseStatusCodeSame(204);
        foreach ([Json::string($first, 'url'), Json::string($first, 'thumbUrl')] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertCount(1, Json::array(Json::decode((string) $client->getResponse()->getContent()), 0, 'photos'));
    }

    public function testAPhotoCannotBeDeletedOrReorderedThroughAnotherProduct(): void
    {
        $client = self::signedInClient();
        [$fox, $owl] = self::push([self::fox(), self::owl()]);
        $foxPhoto = Json::string(self::upload($client, $fox), 'photos', 0, 'id');
        $owlPhoto = Json::string(self::upload($client, $owl), 'photos', 0, 'id');

        $client->jsonRequest('DELETE', "/api/admin/products/{$owl}/photos/{$foxPhoto}");
        self::assertResponseStatusCodeSame(404);

        $client->jsonRequest('PUT', "/api/admin/products/{$owl}/photos/order", ['ids' => [$foxPhoto, $owlPhoto]]);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('GET', '/api/admin/products');
        $products = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame([[$foxPhoto], [$owlPhoto]], [array_column(Json::array($products, 0, 'photos'), 'id'), array_column(Json::array($products, 1, 'photos'), 'id')]);
    }

    public function testTheProductPagesOfTheAdminComeWithTheProductsAndCategories(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox(new PushedCollection(new Ulid(self::FOLK), 'Forest folk'))])[0];

        foreach (['/admin/products', "/admin/products/{$id}"] as $page) {
            $client->request('GET', $page);
            self::assertResponseIsSuccessful();
            $preloaded = Json::decode($client->getCrawler()->filter('#app-preload')->text());
            self::assertSame(['PRT-FOX'], array_column(Json::array($preloaded, '/api/admin/products'), 'reference'));
            self::assertSame(['Prints'], array_column(Json::array($preloaded, '/api/admin/categories'), 'name'));
            self::assertSame(['Forest folk'], array_column(Json::array($preloaded, '/api/admin/collections'), 'name'));
        }
    }

    public function testAProductCarriesItsCollection(): void
    {
        $client = self::signedInClient();
        self::push([self::fox(new PushedCollection(new Ulid(self::FOLK), 'Forest folk')), self::owl()]);

        $client->jsonRequest('GET', '/api/admin/products');

        $products = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['id' => Json::string($products, 0, 'collection', 'id'), 'name' => 'Forest folk', 'label' => '', 'labelFr' => ''], Json::array($products, 0, 'collection'));
        self::assertNotSame(self::FOLK, Json::string($products, 0, 'collection', 'id'));
        self::assertNull(Json::at($products, 1, 'collection'));
    }

    public function testTheListRunsTheSameQueriesWhateverTheNumberOfCollections(): void
    {
        $client = self::signedInClient();
        self::push([self::fox(new PushedCollection(new Ulid(self::FOLK), 'Forest folk')), self::owl(new PushedCollection(new Ulid(self::FOLK), 'Forest folk'))]);
        $few = self::listQueries($client);

        self::push([self::fox(new PushedCollection(new Ulid(self::FOLK), 'Forest folk')), self::owl(new PushedCollection(new Ulid(self::NIGHT), 'Night owls'))]);

        self::assertSame($few, self::listQueries($client));
    }

    /**
     * @param list<PushedProduct> $products
     *
     * @return list<string>
     */
    private static function push(array $products): array
    {
        self::getContainer()->get(ReplaceCatalogueHandler::class)(new ReplaceCatalogue($products));
        $client = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->jsonRequest('GET', '/api/admin/products');

        return array_values(array_map(static fn (mixed $product): string => Json::string($product, 'id'), Json::decode((string) $client->getResponse()->getContent())));
    }

    private static function fox(PushedCollection|false|null $collection = false): PushedProduct
    {
        return new PushedProduct(new Ulid(self::FOX), 'PRT-FOX', 'Fox', 'Prints', 'Prints Fox', 1500, [new PushedVariant('A4', 'PRT-FOX-A4', 12), new PushedVariant('A5', 'PRT-FOX-A5', 0)], collection: $collection);
    }

    private static function owl(PushedCollection|false|null $collection = false): PushedProduct
    {
        return new PushedProduct(new Ulid(self::OWL), 'STK-OWL', 'Owl', 'Stickers', 'Stickers Owl', 300, [new PushedVariant(null, 'STK-OWL', 4)], collection: $collection);
    }

    private static function listQueries(KernelBrowser $client): int
    {
        $client->enableProfiler();
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /**
     * @param array<string, string> $changes
     *
     * @return array<string, string>
     */
    private static function description(array $changes = []): array
    {
        return $changes + [
            'title' => 'Fox print',
            'titleFr' => 'Affiche renard',
            'description' => 'A fox among ferns.',
            'descriptionFr' => 'Un renard dans les fougères.',
            'slug' => 'prints-fox',
        ];
    }

    /**
     * @return array<mixed>
     */
    private static function upload(KernelBrowser $client, string $product): array
    {
        $client->request('POST', "/api/admin/products/{$product}/photos", files: ['image' => self::file('large-fern.png')], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);

        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function file(string $fixture): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'photo');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/'.$fixture, $copy);

        return new UploadedFile($copy, $fixture, 'image/png', test: true);
    }
}
