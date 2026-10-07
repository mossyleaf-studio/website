<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LogoApiTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testALogoIsResizedAndShownInTheHeader(): void
    {
        $client = self::signedInClient();

        $logo = Json::array(self::upload($client), 'logo');

        self::assertSame([800, 600], [$logo['width'], $logo['height']]);
        $client->request('GET', '/');
        $crawler = $client->getCrawler();
        self::assertSame(Json::string($logo, 'url'), Json::string(Json::decode($crawler->filter('#site-content')->text()), 'logo', 'url'));
        self::assertSame('http://localhost'.Json::string($logo, 'url'), $crawler->filter('meta[property="og:image"]')->attr('content'));
    }

    public function testReplacingTheLogoDeletesTheOldFile(): void
    {
        $client = self::signedInClient();
        $first = Json::string(self::upload($client), 'logo', 'url');

        $second = Json::string(self::upload($client), 'logo', 'url');

        self::assertNotSame($first, $second);
        $client->request('GET', $first);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', $second);
        self::assertResponseIsSuccessful();
    }

    public function testTheLogoIsRemoved(): void
    {
        $client = self::signedInClient();
        $url = Json::string(self::upload($client), 'logo', 'url');

        $client->jsonRequest('DELETE', '/api/admin/logo');

        self::assertResponseIsSuccessful();
        self::assertNull(Json::decode((string) $client->getResponse()->getContent())['logo']);
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<mixed>
     */
    private static function upload(KernelBrowser $client): array
    {
        $copy = tempnam(sys_get_temp_dir(), 'logo');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);

        $client->request('POST', '/api/admin/logo', files: ['image' => new UploadedFile($copy, 'logo.png', 'image/png', test: true)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return Json::decode((string) $client->getResponse()->getContent());
    }
}
