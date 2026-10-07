<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicationApiTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testNothingIsPublishedAtFirst(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/admin/publication');

        self::assertResponseIsSuccessful();
        self::assertSame(['page' => null, 'publishedAt' => null, 'publishedBy' => null, 'pendingChanges' => true], Json::decode((string) $client->getResponse()->getContent()));
    }

    public function testVisitorsOnlySeeWhatWasPublished(): void
    {
        $client = self::signedInClient();
        self::editIntro($client, 'Frogs in the ferns');

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);
        self::assertResponseIsSuccessful();
        $publication = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame(['full', 'Louis', false], [$publication['page'], $publication['publishedBy'], $publication['pendingChanges']]);

        self::editIntro($client, 'Snails on the moss');
        $client->jsonRequest('GET', '/api/admin/publication');
        self::assertTrue(Json::decode((string) $client->getResponse()->getContent())['pendingChanges']);

        $crawler = $client->request('GET', '/');
        self::assertSame('full', $crawler->filter('#site')->attr('data-page'));
        self::assertSame('Frogs in the ferns', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
        self::assertSame('Frogs in the ferns', $crawler->filter('#site header p')->text());

        $crawler = $client->request('GET', '/beta/');
        self::assertSame('Snails on the moss', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
    }

    public function testTheGrowingNoteCanBePublishedAgain(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

        $crawler = $client->request('GET', '/');
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
    }

    public function testAPublishedImageOutlivesItsDeletionFromTheDraftUntilTheNextPublication(): void
    {
        $client = self::signedInClient();
        $client->request('POST', '/api/admin/artworks', files: ['image' => self::image()], server: ['HTTP_ACCEPT' => 'application/json']);
        $artwork = Json::decode((string) $client->getResponse()->getContent());
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        $client->jsonRequest('DELETE', '/api/admin/artworks/'.Json::string($artwork, 'id'));
        $client->request('GET', Json::string($artwork, 'url'));
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);
        $client->request('GET', Json::string($artwork, 'url'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testPublishingNeedsAnAdmin(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        self::assertResponseStatusCodeSame(401);
    }

    private static function editIntro(KernelBrowser $client, string $intro): void
    {
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => $intro, 'metaDescription' => 'Drawings']);
        self::assertResponseIsSuccessful();
    }

    private static function image(): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'artwork');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);

        return new UploadedFile($copy, 'fern.png', 'image/png', test: true);
    }
}
