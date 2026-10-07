<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoTest extends WebTestCase
{
    use SignsInClient;

    public function testTheHomePageCarriesItsContentWithoutJavaScript(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About the studio', 'text' => 'Order on [Etsy](https://www.etsy.com/shop/mossyleafstudio) <b>now</b>.']);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        $crawler = self::asVisitor($client)->request('GET', '/');

        self::assertSame('mossyleaf.studio', $crawler->filter('#site h1')->text());
        self::assertSame(['Shop on Etsy', 'Follow on Instagram'], $crawler->filter('#site nav a')->each(static fn ($link): string => $link->text()));
        self::assertSame('https://www.etsy.com/shop/mossyleafstudio', $crawler->filter('#site section p a')->attr('href'));
        self::assertStringContainsString('&lt;b&gt;now&lt;/b&gt;', $crawler->filter('#site section p')->html());
    }

    public function testTheHomePageDeclaresItselfToSearchEngines(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Critters', 'metaDescription' => 'Hand-drawn critters.', 'searchTitle' => 'mossyleaf.studio – critter illustrations']);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        $crawler = self::asVisitor($client)->request('GET', '/');

        self::assertSelectorTextSame('title', 'mossyleaf.studio – critter illustrations');
        self::assertSame('http://localhost/', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('Hand-drawn critters.', $crawler->filter('meta[property="og:description"]')->attr('content'));
        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertSame('Organization', Json::string($graph, 0, '@type'));
        self::assertSame(['https://www.etsy.com/shop/mossyleafstudio', 'https://www.instagram.com/mossyleaf.studio/'], Json::array($graph, 0, 'sameAs'));
        self::assertSame('WebSite', Json::string($graph, 1, '@type'));
    }

    public function testAnUnchangedPublicationAnswersNotModified(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);
        $visitor = self::asVisitor($client);
        $visitor->request('GET', '/');
        $etag = (string) $visitor->getResponse()->headers->get('ETag');

        $visitor->request('GET', '/', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertResponseStatusCodeSame(304);
    }

    public function testRobotsKeepCrawlersOnThePublicSite(): void
    {
        $client = self::createClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $robots = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("Disallow: /admin\nDisallow: /beta\n", $robots);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $robots);
    }

    public function testTheSitemapListsTheHomePageWithItsPublicationDate(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'full']);

        $client->request('GET', '/sitemap.xml');

        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        self::assertSame('http://localhost/', (string) $sitemap->url[0]->loc);
        self::assertSame(new \DateTimeImmutable()->format('Y-m-d'), (string) $sitemap->url[0]->lastmod);
    }

    private static function asVisitor(KernelBrowser $client): KernelBrowser
    {
        $client->getCookieJar()->clear();

        return $client;
    }
}
