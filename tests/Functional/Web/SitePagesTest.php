<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitePagesTest extends WebTestCase
{
    use SignsInClient;

    public function testTheHomePageCarriesTheContentAndItsMetaTags(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'mossyleaf.studio');
        self::assertSame('Illustrations of little critters among leaves and moss, drawn by hand.', $crawler->filter('meta[property="og:description"]')->attr('content'));
        self::assertCount(0, $crawler->filter('meta[name="robots"]'));
        self::assertSame('home', $crawler->filter('#site')->attr('data-page'));
        $content = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame('The website is still growing', Json::string($content, 'homeNote', 'title'));
        self::assertSame(['Shop on Etsy', 'Follow on Instagram'], array_column(Json::array($content, 'links'), 'title'));
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
    }

    public function testTheFullPageIsHiddenFromSearchEngines(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/beta/');

        self::assertResponseIsSuccessful();
        self::assertSame('noindex', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('beta', $crawler->filter('#site')->attr('data-page'));
    }

    public function testTheFullPageAddressWithoutSlashRedirects(): void
    {
        $client = self::createClient();

        $client->request('GET', '/beta');

        self::assertResponseRedirects('http://localhost/beta/', 301);
    }

    public function testEditedTextsShowOnTheSiteEscaped(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Ferns </script><b>and</b> frogs', 'metaDescription' => 'Drawings']);
        self::assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/');

        self::assertStringNotContainsString('</script><b>', (string) $client->getResponse()->getContent());
        self::assertSame('Ferns </script><b>and</b> frogs', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'intro'));
        self::assertSame('Drawings', $crawler->filter('meta[name="description"]')->attr('content'));
    }
}
