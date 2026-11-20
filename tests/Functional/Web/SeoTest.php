<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;

final class SeoTest extends WebTestCase
{
    use OpensTheShop;
    use PushesCatalogue;
    use SignsInClient;

    private const string SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const string XHTML = 'http://www.w3.org/1999/xhtml';

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testTheHomePageCarriesItsContentWithoutJavaScript(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/home-note', ['title' => 'Still growing', 'text' => 'Order on [Etsy](https://www.etsy.com/shop/mossyleafstudio) <b>now</b>.']);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

        $crawler = self::asVisitor($client)->request('GET', '/');

        self::assertSame('mossyleaf.studio', $crawler->filter('#site h1')->text());
        self::assertSame('Still growing', $crawler->filter('#site section h2')->text());
        self::assertSame('https://www.etsy.com/shop/mossyleafstudio', $crawler->filter('#site section p a')->attr('href'));
        self::assertStringContainsString('&lt;b&gt;now&lt;/b&gt;', $crawler->filter('#site section p')->html());
    }

    public function testTheHomePageDeclaresItselfToSearchEngines(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Critters', 'metaDescription' => 'Hand-drawn critters.', 'searchTitle' => 'mossyleaf.studio – critter illustrations']);
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

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
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);
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
        self::assertSame("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /beta\nDisallow: /api/\nDisallow: /login\nDisallow: /logout\n\nSitemap: http://localhost/sitemap.xml\n", (string) $client->getResponse()->getContent());
    }

    public function testOnceTheShopIsOpenRobotsAlsoKeepCrawlersAwayFromTheOrders(): void
    {
        $client = self::openShopClient();

        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertSame("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /beta\nDisallow: /api/\nDisallow: /login\nDisallow: /logout\nDisallow: /en/order/\nDisallow: /fr/order/\n\nSitemap: http://localhost/sitemap.xml\n", (string) $client->getResponse()->getContent());
    }

    public function testTheSitemapListsTheHomePageWithItsPublicationDate(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/admin/publication', ['page' => 'note']);

        $client->request('GET', '/sitemap.xml');

        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        self::assertCount(1, $sitemap->url);
        self::assertSame('http://localhost/', (string) $sitemap->url[0]->loc);
        self::assertSame(new \DateTimeImmutable()->format('Y-m-d'), (string) $sitemap->url[0]->lastmod);
    }

    public function testBeforeAnyPublicationTheSitemapListsTheHomePageAlone(): void
    {
        $client = self::createClient();

        $client->request('GET', '/sitemap.xml');

        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        self::assertCount(1, $sitemap->url);
        self::assertSame('http://localhost/', (string) $sitemap->url[0]->loc);
        self::assertSame('', (string) $sitemap->url[0]->lastmod);
    }

    public function testTheSitemapOfTheOpenShopListsEveryPublicPageInBothLanguages(): void
    {
        $client = self::openShopClient();

        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        self::assertResponseNotHasHeader('Set-Cookie');
        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        $pages = ['', 'shop', 'shop/prints-fox', 'shop/prints-owl', 'conventions', 'about', 'contact', 'legal/terms', 'legal/legal-notice', 'legal/privacy'];
        $expected = [];
        foreach ($pages as $page) {
            foreach (['en', 'fr'] as $locale) {
                $expected[] = "http://localhost/{$locale}/{$page}";
            }
        }
        $urls = [];
        foreach ($sitemap->children(self::SITEMAP)->url as $url) {
            $urls[] = (string) $url->loc;
            $path = substr((string) $url->loc, \strlen('http://localhost/en/'));
            $alternates = [];
            foreach ($url->children(self::XHTML)->link as $link) {
                $attributes = $link->attributes();
                self::assertNotNull($attributes);
                self::assertSame('alternate', (string) $attributes['rel']);
                $alternates[(string) $attributes['hreflang']] = (string) $attributes['href'];
            }
            self::assertSame(['fr' => "http://localhost/fr/{$path}", 'en' => "http://localhost/en/{$path}", 'x-default' => "http://localhost/en/{$path}"], $alternates, (string) $url->loc);
        }
        self::assertSame($expected, $urls);
        self::assertDoesNotMatchRegularExpression('{cart|checkout|order|stickers-moth|prints-hare|cards-bee|shipping-returns|/beta/}', (string) $client->getResponse()->getContent());
    }

    public function testTheHomeOfTheOpenShopDeclaresTheStudioInTheLanguageOfThePage(): void
    {
        $client = self::openShopClient();

        foreach (['en' => 'Illustrations of little critters among leaves and moss, drawn by hand.', 'fr' => 'Des petites bêtes dessinées à la main.'] as $locale => $description) {
            $crawler = $client->request('GET', "/{$locale}/");

            $home = "http://localhost/{$locale}/";
            $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
            self::assertSame('https://schema.org', Json::string(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@context'));
            self::assertSame(['Organization', $home.'#studio', 'mossyleaf.studio', $home, $description], [Json::string($graph, 0, '@type'), Json::string($graph, 0, '@id'), Json::string($graph, 0, 'name'), Json::string($graph, 0, 'url'), Json::string($graph, 0, 'description')]);
            self::assertSame(['https://www.etsy.com/shop/mossyleafstudio', 'https://www.instagram.com/mossyleaf.studio/'], Json::array($graph, 0, 'sameAs'));
            self::assertSame(['WebSite', $home.'#website', $home, $locale, $home.'#studio'], [Json::string($graph, 1, '@type'), Json::string($graph, 1, '@id'), Json::string($graph, 1, 'url'), Json::string($graph, 1, 'inLanguage'), Json::string($graph, 1, 'publisher', '@id')]);
        }

        $client->request('GET', '/en/about');
        self::assertStringNotContainsString('application/ld+json', (string) $client->getResponse()->getContent());
    }

    public function testAFilteredListingPointsSearchEnginesAtTheWholeListingAndKeepsItsFilterAcrossLanguages(): void
    {
        $client = self::openShopClient();

        $crawler = $client->request('GET', '/fr/shop?category=prints');

        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/fr/shop', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame(['en' => 'http://localhost/en/shop', 'fr' => 'http://localhost/fr/shop', 'x-default' => 'http://localhost/en/shop'], self::alternates($crawler));
        $languages = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'languages');
        self::assertSame([['locale' => 'en', 'url' => '/en/shop?category=prints', 'current' => false], ['locale' => 'fr', 'url' => '/fr/shop?category=prints', 'current' => true]], $languages);
        self::assertSame(['/en/shop?category=prints', '/fr/shop?category=prints'], $crawler->filter('header nav[data-languages] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));

        $crawler = $client->request('GET', '/fr/shop');
        self::assertSame([['locale' => 'en', 'url' => '/en/shop', 'current' => false], ['locale' => 'fr', 'url' => '/fr/shop', 'current' => true]], Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'languages'));
    }

    public function testTheShopEntryIsCurrentOnTheListingAndOnAProductPage(): void
    {
        $client = self::openShopClient();

        foreach (['/en/shop', '/en/shop?category=prints', '/en/shop/prints-fox'] as $url) {
            $crawler = $client->request('GET', $url);

            $navigation = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'navigation');
            self::assertSame(['shop'], array_values(array_map(static fn (mixed $entry): string => Json::string($entry, 'key'), array_filter($navigation, static fn (mixed $entry): bool => true === Json::at($entry, 'current')))), $url);
            self::assertSame(['/en/shop'], $crawler->filter('nav[data-navigation] a[aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')), $url);
        }

        $crawler = $client->request('GET', '/en/about');
        self::assertSame(['/en/about'], $crawler->filter('nav[data-navigation] a[aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
    }

    private static function openShopClient(): KernelBrowser
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Little critters.', 'metaDescription' => 'Illustrations of little critters among leaves and moss, drawn by hand.', 'metaDescriptionFr' => 'Des petites bêtes dessinées à la main.']);
        self::stockTheDemo($client);
        self::openTheShop($client);

        return self::asVisitor($client);
    }

    /** @return array<string, string> */
    private static function alternates(Crawler $crawler): array
    {
        return array_combine(
            $crawler->filter('link[rel="alternate"][hreflang]')->each(static fn (Crawler $link): string => (string) $link->attr('hreflang')),
            $crawler->filter('link[rel="alternate"][hreflang]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
        );
    }

    private static function asVisitor(KernelBrowser $client): KernelBrowser
    {
        $client->getCookieJar()->clear();

        return $client;
    }
}
