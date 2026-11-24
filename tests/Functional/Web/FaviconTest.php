<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class FaviconTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testWithoutALogoOrWithItsFileMissingThereIsNoFavicon(): void
    {
        $client = self::signedInClient();
        $client->getCookieJar()->clear();

        $client->request('GET', '/favicon');

        self::assertResponseStatusCodeSame(404);
        self::assertPublicAndCookieless($client->getResponse());

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $logo = self::upload($client, 'fern');
        new Filesystem()->remove(self::file($logo));
        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon');

        self::assertResponseStatusCodeSame(404);
        self::assertPublicAndCookieless($client->getResponse());
    }

    public function testBeforeAnyPublicationTheFaviconIsTheDraftLogoOnCream(): void
    {
        $client = self::signedInClient();
        self::upload($client, 'fern');

        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon');

        self::assertSame([180, 180], self::dimensions(self::picture($client)));
    }

    public function testOncePublishedTheFaviconIsThePublishedLogoEvenWhenTheDraftChanged(): void
    {
        $client = self::signedInClient();
        $published = self::upload($client, 'fern');
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
        self::assertResponseIsSuccessful();

        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon');
        $before = self::picture($client);
        $etag = (string) $client->getResponse()->headers->get('ETag');

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $draft = self::upload($client, 'berry');
        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon');

        self::assertNotSame($published, $draft);
        self::assertSame($before, self::picture($client));
        self::assertNotSame('', $etag);
        self::assertSame($etag, $client->getResponse()->headers->get('ETag'));
    }

    public function testTheETagFollowsTheLogoAndAMatchAnswersNotModified(): void
    {
        $client = self::signedInClient();
        self::upload($client, 'fern');
        $client->request('GET', '/favicon');
        $png = self::picture($client);
        $first = (string) $client->getResponse()->headers->get('ETag');
        self::assertSame('"'.hash('xxh128', $png).'"', $first);

        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon', server: ['HTTP_IF_NONE_MATCH' => $first]);
        self::assertResponseStatusCodeSame(304);
        self::assertPublicAndCookieless($client->getResponse());

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        self::upload($client, 'berry');
        $client->getCookieJar()->clear();
        $client->request('GET', '/favicon', server: ['HTTP_IF_NONE_MATCH' => $first]);
        self::picture($client);

        self::assertNotSame($first, $client->getResponse()->headers->get('ETag'));
    }

    public function testThePagesLinkTheFaviconAsIconAndTouchIcon(): void
    {
        $crawler = self::createClient()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('/favicon', $crawler->filter('link[rel="icon"]')->attr('href'));
        self::assertSame('image/png', $crawler->filter('link[rel="icon"]')->attr('type'));
        self::assertSame('/favicon', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
    }

    private static function picture(KernelBrowser $client): string
    {
        $response = $client->getResponse();
        self::assertResponseStatusCodeSame(200);
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertNotEmpty($response->headers->get('ETag'));
        self::assertPublicAndCookieless($response);

        return (string) $response->getContent();
    }

    private static function assertPublicAndCookieless(Response $response): void
    {
        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    /** @return array{int, int} */
    private static function dimensions(string $png): array
    {
        $size = getimagesizefromstring($png);
        self::assertIsArray($size);
        self::assertSame(\IMAGETYPE_PNG, $size[2]);

        return [$size[0], $size[1]];
    }

    private static function file(string $url): string
    {
        $share = self::getContainer()->getParameter('kernel.share_dir');

        return $share.'/artworks/'.basename($url);
    }

    private static function upload(KernelBrowser $client, string $name): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'logo');
        self::assertIsString($copy);
        $logo = imagecreatetruecolor('fern' === $name ? 400 : 120, 200);
        self::assertInstanceOf(\GdImage::class, $logo);
        imagefilledrectangle($logo, 0, 0, 399, 199, (int) imagecolorallocate($logo, 'fern' === $name ? 0x2F : 0xA0, 0x5D, 0x3A));
        imagepng($logo, $copy);

        $client->request('POST', '/api/admin/logo', files: ['image' => new UploadedFile($copy, 'logo.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'logo', 'url');
    }
}
