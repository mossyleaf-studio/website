<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FaviconTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testWithoutALogoTheFaviconIsTheBundledOne(): void
    {
        $client = self::createClient();

        $client->request('GET', '/favicon');

        self::assertSame('/favicon.svg', self::target($client));
    }

    public function testBeforeAnyPublicationTheFaviconIsTheDraftLogo(): void
    {
        $client = self::signedInClient();
        $logo = self::upload($client);

        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon');

        self::assertStringStartsWith('/media/artworks/', $logo);
        self::assertSame($logo, self::target($client));
    }

    public function testOncePublishedTheFaviconIsThePublishedLogoEvenWhenTheDraftChanged(): void
    {
        $client = self::signedInClient();
        $published = self::upload($client);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertResponseIsSuccessful();
        $draft = self::upload($client);

        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon');

        self::assertNotSame($published, $draft);
        self::assertSame($published, self::target($client));
    }

    public function testTheNoteAndTheShopPreviewLinkTheFavicon(): void
    {
        $client = self::signedInClient();

        foreach (['/', '/beta/note/', '/beta/en/', '/admin'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            self::assertSame('/favicon', $crawler->filter('link[rel="icon"]')->attr('href'), $url);
            self::assertCount(0, $crawler->filter('link[rel="apple-touch-icon"]'), $url);
        }
    }

    private static function target(KernelBrowser $client): string
    {
        $response = $client->getResponse();
        self::assertResponseStatusCodeSame(302);
        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));

        return (string) $response->headers->get('Location');
    }

    private static function upload(KernelBrowser $client): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'logo');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);

        $client->request('POST', '/api/admin/logo', files: ['image' => new UploadedFile($copy, 'logo.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'logo', 'url');
    }
}
