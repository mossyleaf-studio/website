<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Domain\Shared\Translation;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final class ProductsApiTest extends WebTestCase
{
    use SignsInClient;

    private const string FOX = '01JD3K7Q0000000000000000F0';
    private const string OWL = '01JD3K7Q0000000000000000F1';

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
        self::assertSame([
            'id' => Json::string($products, 0, 'id'),
            'reference' => 'PRT-FOX',
            'name' => 'Fox',
            'displayName' => 'Prints Fox',
            'category' => ['id' => Json::string($products, 0, 'category', 'id'), 'name' => 'Prints', 'label' => '', 'labelFr' => ''],
            'price' => 1500,
            'variants' => [['sku' => 'PRT-FOX-A4', 'name' => 'A4', 'stock' => 12], ['sku' => 'PRT-FOX-A5', 'name' => 'A5', 'stock' => 0]],
            'retired' => false,
            'slug' => 'prints-fox',
            'title' => '',
            'titleFr' => '',
            'description' => '',
            'descriptionFr' => '',
            'visible' => false,
            'picked' => false,
            'position' => 0,
            'photos' => [],
            'preview' => ['en' => '/beta/en/shop/prints-fox', 'fr' => '/beta/fr/shop/prints-fox'],
        ], Json::array($products, 0));
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

        self::assertSame($renamed, Json::array(Json::decode((string) $client->getResponse()->getContent()), 'preview'));

        $client->jsonRequest('DELETE', "/api/admin/products/{$fox}/visibility");

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

    public function testTheProductCarriesTheDescriptionsThePushFilled(): void
    {
        $client = self::signedInClient();
        self::push([new PushedProduct(new Ulid(self::FOX), 'PRT-FOX', 'Fox', 'Prints', 'Prints Fox', 1500, [new PushedVariant(null, 'PRT-FOX', 1)], Translation::of('A fox among ferns.', "Un renard.\n\nDans les fougères."))]);

        $client->jsonRequest('GET', '/api/admin/products');

        $fox = Json::array(Json::decode((string) $client->getResponse()->getContent()), 0);
        self::assertSame(['A fox among ferns.', "Un renard.\n\nDans les fougères.", '', ''], [$fox['description'], $fox['descriptionFr'], $fox['title'], $fox['titleFr']]);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function textsOverTheirLimit(): iterable
    {
        yield 'title' => [['title' => str_repeat('a', 121)]];
        yield 'French title' => [['titleFr' => str_repeat('é', 121)]];
        yield 'description' => [['description' => str_repeat('a', 4001)]];
        yield 'French description' => [['descriptionFr' => str_repeat('é', 4001)]];
    }

    /**
     * @param array<string, string> $changes
     */
    #[DataProvider('textsOverTheirLimit')]
    public function testATextOverItsLimitIsRefused(array $changes): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        $client->jsonRequest('PUT', "/api/admin/products/{$id}", self::description($changes));

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnInvalidSlugIsRefused(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        $client->jsonRequest('PUT', "/api/admin/products/{$id}", self::description(['slug' => 'Fox print']));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('lowercase', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
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

    public function testAProductIsShownAndHidden(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];

        $client->jsonRequest('PUT', "/api/admin/products/{$id}/visibility");
        self::assertResponseIsSuccessful();
        self::assertTrue(Json::decode((string) $client->getResponse()->getContent())['visible']);

        $client->jsonRequest('DELETE', "/api/admin/products/{$id}/visibility");
        self::assertResponseIsSuccessful();
        self::assertFalse(Json::decode((string) $client->getResponse()->getContent())['visible']);
    }

    public function testARetiredProductCannotBeShown(): void
    {
        $client = self::signedInClient();
        [, $owl] = self::push([self::fox(), self::owl()]);
        self::push([self::fox()]);

        $client->jsonRequest('PUT', "/api/admin/products/{$owl}/visibility");

        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame([false, true], array_column(Json::decode((string) $client->getResponse()->getContent()), 'retired'));
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

    public function testARetiredProductCannotBePickedButCanBeUnpicked(): void
    {
        $client = self::signedInClient();
        [, $owl] = self::push([self::fox(), self::owl()]);
        $client->jsonRequest('PUT', "/api/admin/products/{$owl}/pick");
        self::push([self::fox()]);

        $client->jsonRequest('PUT', "/api/admin/products/{$owl}/pick");

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('catalogue.product_retired', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'code'));

        $client->jsonRequest('DELETE', "/api/admin/products/{$owl}/pick");

        self::assertResponseIsSuccessful();
        self::assertFalse(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'picked'));
    }

    public function testPickingAnUnknownProductIsNotFound(): void
    {
        $client = self::signedInClient();

        foreach (['PUT', 'DELETE'] as $method) {
            $client->jsonRequest($method, '/api/admin/products/'.new Ulid().'/pick');

            self::assertResponseStatusCodeSame(404, $method);
        }
    }

    public function testAnAnonymousCallerCannotPick(): void
    {
        $client = self::createClient();
        self::getContainer()->get(ReplaceCatalogueHandler::class)(new ReplaceCatalogue([self::fox()]));
        $id = self::getContainer()->get(CatalogueQueries::class)->products()[0]->id;

        foreach (['PUT', 'DELETE'] as $method) {
            $client->jsonRequest($method, "/api/admin/products/{$id}/pick");

            self::assertResponseStatusCodeSame(401, $method);
        }
        self::assertFalse(self::getContainer()->get(CatalogueQueries::class)->products()[0]->picked);
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

    /**
     * @return iterable<string, array{callable(string, string): list<string>}>
     */
    public static function ordersThatAreNotTheCurrentSet(): iterable
    {
        yield 'one missing' => [static fn (string $fox, string $owl): array => [$owl]];
        yield 'one twice' => [static fn (string $fox, string $owl): array => [$owl, $owl]];
        yield 'one unknown' => [static fn (string $fox, string $owl): array => [$owl, $fox, (string) new Ulid()]];
    }

    /**
     * @param callable(string, string): list<string> $order
     */
    #[DataProvider('ordersThatAreNotTheCurrentSet')]
    public function testAnOrderThatIsNotExactlyTheCurrentSetIsRefused(callable $order): void
    {
        $client = self::signedInClient();
        [$fox, $owl] = self::push([self::fox(), self::owl()]);

        $client->jsonRequest('PUT', '/api/admin/products/order', ['ids' => $order($fox, $owl)]);

        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('GET', '/api/admin/products');
        self::assertSame([$fox, $owl], array_column(Json::decode((string) $client->getResponse()->getContent()), 'id'));
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

    public function testPhotosAreReordered(): void
    {
        $client = self::signedInClient();
        $id = self::push([self::fox()])[0];
        self::upload($client, $id);
        $photos = array_column(Json::array(self::upload($client, $id), 'photos'), 'id');

        $client->jsonRequest('PUT', "/api/admin/products/{$id}/photos/order", ['ids' => array_reverse($photos)]);

        self::assertResponseIsSuccessful();
        self::assertSame(array_reverse($photos), array_column(Json::array(Json::decode((string) $client->getResponse()->getContent()), 'photos'), 'id'));
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
        $id = self::push([self::fox()])[0];

        foreach (['/admin/products', "/admin/products/{$id}"] as $page) {
            $client->request('GET', $page);
            self::assertResponseIsSuccessful();
            $preloaded = Json::decode($client->getCrawler()->filter('#app-preload')->text());
            self::assertSame(['PRT-FOX'], array_column(Json::array($preloaded, '/api/admin/products'), 'reference'));
            self::assertSame(['Prints'], array_column(Json::array($preloaded, '/api/admin/categories'), 'name'));
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        $product = '/api/admin/products/'.self::FOX;
        $photo = $product.'/photos/'.self::OWL;

        yield 'list' => ['GET', '/api/admin/products'];
        yield 'describe' => ['PUT', $product];
        yield 'show' => ['PUT', $product.'/visibility'];
        yield 'hide' => ['DELETE', $product.'/visibility'];
        yield 'reorder' => ['PUT', '/api/admin/products/order'];
        yield 'upload a photo' => ['POST', $product.'/photos'];
        yield 'delete a photo' => ['DELETE', $photo];
        yield 'reorder the photos' => ['PUT', $product.'/photos/order'];
    }

    #[DataProvider('routes')]
    public function testEveryRouteNeedsASignedInEditor(string $method, string $url): void
    {
        $client = self::createClient();

        $client->jsonRequest($method, $url);

        self::assertResponseStatusCodeSame(401);
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

    private static function fox(): PushedProduct
    {
        return new PushedProduct(new Ulid(self::FOX), 'PRT-FOX', 'Fox', 'Prints', 'Prints Fox', 1500, [new PushedVariant('A4', 'PRT-FOX-A4', 12), new PushedVariant('A5', 'PRT-FOX-A5', 0)]);
    }

    private static function owl(): PushedProduct
    {
        return new PushedProduct(new Ulid(self::OWL), 'STK-OWL', 'Owl', 'Stickers', 'Stickers Owl', 300, [new PushedVariant(null, 'STK-OWL', 4)]);
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
