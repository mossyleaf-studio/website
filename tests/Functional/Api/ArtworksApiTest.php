<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ArtworksApiTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testALargeImageIsResizedToWebpAndServedFromMedia(): void
    {
        $client = self::signedInClient();

        $artwork = self::upload($client, 'large-fern.png', 'A big fern');

        self::assertSame([1600, 1200, 'A big fern', false], [$artwork['width'], $artwork['height'], $artwork['alt'], $artwork['featured']]);
        $url = $artwork['url'];
        self::assertIsString($url);
        self::assertMatchesRegularExpression('#^/media/artworks/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', $url);

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertStringContainsString('immutable', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testSomethingThatIsNotAnImageIsRefused(): void
    {
        $client = self::signedInClient();

        $client->request('POST', '/api/admin/artworks', files: ['image' => self::file('not-an-image.png')], server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('JPEG, PNG, WebP or AVIF', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }

    public function testOnlyOneArtworkIsFeaturedAtATime(): void
    {
        $client = self::signedInClient();
        $first = Json::string(self::upload($client, 'large-fern.png'), 'id');
        $second = Json::string(self::upload($client, 'large-fern.png'), 'id');

        $client->jsonRequest('PUT', "/api/admin/artworks/{$first}/featured");
        $client->jsonRequest('PUT', "/api/admin/artworks/{$second}/featured");

        self::assertResponseIsSuccessful();
        $featured = array_column(Json::decode((string) $client->getResponse()->getContent()), 'featured', 'id');
        self::assertSame([$first => false, $second => true], $featured);

        $client->request('GET', '/');
        $content = Json::decode($client->getCrawler()->filter('#site-content')->text());
        self::assertSame($second, Json::string($content, 'featured', 'id'));
        self::assertSame([$first], array_column(Json::array($content, 'gallery', 'artworks'), 'id'));
    }

    public function testAnArtworkIsDescribedReorderedAndDeletedWithItsFile(): void
    {
        $client = self::signedInClient();
        $first = self::upload($client, 'large-fern.png');
        $second = self::upload($client, 'large-fern.png');

        $client->jsonRequest('PUT', '/api/admin/artworks/'.Json::string($first, 'id'), ['alt' => 'A fern unrolling']);
        self::assertSame('A fern unrolling', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'alt'));

        $client->jsonRequest('PUT', '/api/admin/artworks/order', ['ids' => [Json::string($second, 'id'), Json::string($first, 'id')]]);
        self::assertSame([Json::string($second, 'id'), Json::string($first, 'id')], array_column(Json::decode((string) $client->getResponse()->getContent()), 'id'));

        $client->jsonRequest('DELETE', '/api/admin/artworks/'.Json::string($first, 'id'));
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', Json::string($first, 'url'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testUploadsNeedASignedInEditor(): void
    {
        $client = self::createClient();

        $client->request('POST', '/api/admin/artworks', files: ['image' => self::file('large-fern.png')], server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<mixed>
     */
    private static function upload(KernelBrowser $client, string $fixture, string $alt = ''): array
    {
        $client->request('POST', '/api/admin/artworks', ['alt' => $alt], ['image' => self::file($fixture)], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);

        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function file(string $fixture): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'artwork');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/'.$fixture, $copy);

        return new UploadedFile($copy, $fixture, 'image/png', test: true);
    }
}
