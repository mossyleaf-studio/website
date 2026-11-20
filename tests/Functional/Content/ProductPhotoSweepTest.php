<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogue;
use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final class ProductPhotoSweepTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testAProductPhotoSurvivesEverySweepUntilItIsDeleted(): void
    {
        $client = self::signedInClient();
        self::getContainer()->get(ReplaceCatalogueHandler::class)(new ReplaceCatalogue([
            new PushedProduct(new Ulid(), 'PRT-FOX', 'Fox', 'Prints', 'Prints Fox', 1500, [new PushedVariant(null, 'PRT-FOX', 1)]),
        ]));
        $client->jsonRequest('GET', '/api/admin/products');
        $product = Json::string(Json::decode((string) $client->getResponse()->getContent()), 0, 'id');
        $photo = Json::array(Json::decode((string) self::upload($client, "/api/admin/products/{$product}/photos")), 'photos', 0);
        $files = [Json::string($photo, 'url'), Json::string($photo, 'thumbUrl')];
        $artwork = Json::string(Json::decode((string) self::upload($client, '/api/admin/artworks')), 'id');

        $client->jsonRequest('DELETE', "/api/admin/artworks/{$artwork}");
        self::assertResponseStatusCodeSame(204);
        self::assertServed($client, $files, 200);

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertResponseIsSuccessful();
        self::assertServed($client, $files, 200);

        $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => 'Caveat']);
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => '']);
        self::assertResponseIsSuccessful();
        self::assertServed($client, $files, 200);

        $client->jsonRequest('DELETE', "/api/admin/products/{$product}/photos/".Json::string($photo, 'id'));
        self::assertResponseStatusCodeSame(204);
        self::assertServed($client, $files, 404);
    }

    /**
     * @param list<string> $urls
     */
    private static function assertServed(KernelBrowser $client, array $urls, int $status): void
    {
        foreach ($urls as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame($status, $url);
        }
    }

    private static function upload(KernelBrowser $client, string $url): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'photo');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);

        $client->request('POST', $url, files: ['image' => new UploadedFile($copy, 'fern.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);

        return (string) $client->getResponse()->getContent();
    }
}
