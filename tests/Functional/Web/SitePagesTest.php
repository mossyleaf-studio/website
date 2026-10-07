<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitePagesTest extends WebTestCase
{
    use SignsInClient;

    public function testBeforeAnyPublicationTheHomePageShowsTheGrowingNote(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'mossyleaf.studio');
        self::assertSame('Illustrations of little critters among leaves and moss, drawn by hand.', $crawler->filter('#site header p')->text());
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
        self::assertNull($crawler->filter('#site')->attr('data-draft'));
        $content = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame('The website is still growing', Json::string($content, 'homeNote', 'title'));
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
    }

    public function testTheDraftPagesAreForAdminsOnly(): void
    {
        $client = self::createClient();

        foreach (['/beta/', '/beta/note/'] as $url) {
            $client->request('GET', $url);
            self::assertResponseRedirects('/login');
        }
    }

    public function testAdminsPreviewTheDraftHiddenFromSearchEngines(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beta/');
        self::assertResponseIsSuccessful();
        self::assertSame('full', $crawler->filter('#site')->attr('data-page'));
        self::assertSame('', $crawler->filter('#site')->attr('data-draft'));
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');

        $crawler = $client->request('GET', '/beta/note/');
        self::assertSame('note', $crawler->filter('#site')->attr('data-page'));
    }

    public function testPagesStayUpWhenTheSameKernelServesSeveralRequests(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();

        foreach (['/', '/beta/', '/', '/beta/note/'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
        }
    }

    public function testTheDraftAddressWithoutSlashRedirects(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/beta');

        self::assertResponseRedirects('http://localhost/beta/', 301);
    }

    public function testEditedTextsShowEscaped(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Ferns </script><b>and</b> frogs', 'metaDescription' => 'Drawings']);
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/beta/');

        self::assertStringNotContainsString('</script><b>', (string) $client->getResponse()->getContent());
        self::assertSame('Ferns </script><b>and</b> frogs', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
        self::assertSame('Drawings', $crawler->filter('meta[name="description"]')->attr('content'));
    }

    public function testTheChosenFontsReachVisitorsOnceThePageIsPublished(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/heading', ['family' => 'Caveat']);
        $client->jsonRequest('PUT', '/api/admin/texts/fonts/body', ['family' => 'Patrick Hand']);
        self::assertResponseIsSuccessful();
        $style = "--font-display: 'Caveat', cursive; --font-display-weight: 700;--font-body: 'Patrick Hand', cursive; --font-body-weight: 400;";

        $crawler = $client->request('GET', '/beta/');
        self::assertSame($style, $crawler->filter('html')->attr('style'));
        self::assertCount(2, $crawler->filter('link[rel="stylesheet"][href^="/media/fonts/"]'));

        $crawler = $client->request('GET', '/');
        self::assertNull($crawler->filter('html')->attr('style'));
        self::assertCount(0, $crawler->filter('link[href^="/media/fonts/"]'));

        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);
        $crawler = $client->request('GET', '/');
        self::assertSame($style, $crawler->filter('html')->attr('style'));
    }
}
