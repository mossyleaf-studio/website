<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Content\ContentQueries;
use App\Application\Content\SiteSnapshots;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ShopPagesTest extends WebTestCase
{
    use PushesCatalogue;
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testEditorsPreviewTheFrenchHomeWithTheEnglishFallback(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Little critters.', 'metaDescription' => 'Hand-drawn critters.', 'introFr' => 'Petites bêtes dessinées.', 'metaDescriptionFr' => 'Des bestioles à la main.']);
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About the studio', 'text' => "A tiny studio.\n\nIt draws ferns.", 'titleFr' => 'Le studio', 'textFr' => '']);
        $client->jsonRequest('PUT', '/api/admin/texts/shop-notes', ['announcement' => 'Free shipping', 'announcementFr' => 'Livraison offerte', 'priceNote' => '', 'priceNoteFr' => '']);
        $featured = self::uploadArtwork($client, 'A fern', 'Une fougère');
        $client->jsonRequest('PUT', "/api/admin/artworks/{$featured}/featured");

        $crawler = $client->request('GET', '/beta/fr/', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9']);

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('shop', $crawler->filter('html')->attr('data-layout'));
        self::assertSame('fr', $crawler->filter('body')->attr('data-locale'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('#cbd3a0', $crawler->filter('meta[name="theme-color"]')->attr('content'));

        $props = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame('fr', Json::string($props, 'layout', 'locale'));
        self::assertTrue(Json::at($props, 'layout', 'preview'));
        self::assertSame('Livraison offerte', Json::string($props, 'layout', 'announcement'));
        self::assertSame('home', Json::string($props, 'data', 'page'));
        self::assertSame('Petites bêtes dessinées.', Json::string($props, 'data', 'intro'));
        self::assertSame('Une fougère', Json::string($props, 'data', 'featured', 'alt'));
        self::assertSame(['title' => 'Le studio', 'text' => 'A tiny studio.', 'url' => '/beta/fr/about'], Json::array($props, 'data', 'about'));
        self::assertSame([['key' => 'home', 'url' => '/beta/fr/', 'current' => true], ['key' => 'conventions', 'url' => '/beta/fr/conventions', 'current' => false], ['key' => 'about', 'url' => '/beta/fr/about', 'current' => false], ['key' => 'contact', 'url' => '/beta/fr/contact', 'current' => false]], Json::array($props, 'layout', 'navigation'));
        self::assertSame([['locale' => 'en', 'url' => '/beta/en/', 'current' => false], ['locale' => 'fr', 'url' => '/beta/fr/', 'current' => true]], Json::array($props, 'layout', 'languages'));

        self::assertSame('Petites bêtes dessinées.', $crawler->filter('#shop main h1')->text());
        self::assertSame('A tiny studio.', $crawler->filter('#shop main section p')->first()->text());
        self::assertSame('Livraison offerte', $crawler->filter('#shop [data-announcement]')->text());
        self::assertSame(['shop' => '/beta/fr/', 'note' => '/beta/note/', 'admin' => '/admin'], Json::array($props, 'layout', 'draft'));
        self::assertSame('Des bestioles à la main.', $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertSame('fr_FR', $crawler->filter('meta[property="og:locale"]')->attr('content'));
        self::assertStringStartsWith('http://localhost/media/artworks/', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));

        $internal = array_filter($crawler->filter('#shop .shop-prerender > :not([data-draft]) a')->each(static fn (Crawler $link): string => (string) $link->attr('href')), static fn (string $href): bool => str_starts_with($href, '/'));
        self::assertNotEmpty($internal);
        foreach ($internal as $href) {
            self::assertStringStartsWith('/beta/', $href);
        }
    }

    public function testTheSearchEngineAddressesNeverPointAtThePreview(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/beta/fr/about');

        self::assertSame('http://localhost/fr/about', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame(
            ['en' => 'http://localhost/en/about', 'fr' => 'http://localhost/fr/about', 'x-default' => 'http://localhost/en/about'],
            array_combine(
                $crawler->filter('link[rel="alternate"][hreflang]')->each(static fn (Crawler $link): string => (string) $link->attr('hreflang')),
                $crawler->filter('link[rel="alternate"][hreflang]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
            ),
        );
        self::assertStringNotContainsString('/beta', (string) $crawler->filter('meta[property="og:url"]')->attr('content'));
    }

    public function testTheAboutPageShowsTheTextTheFeaturedArtworkAndTheGallery(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About the studio', 'text' => "A tiny studio.\n\nOrder on [Etsy](https://www.etsy.com/shop/mossyleafstudio).", 'titleFr' => 'Le studio', 'textFr' => "Un petit studio.\n\nIl dessine."]);
        $client->jsonRequest('PUT', '/api/admin/texts/gallery', ['title' => 'Recent drawings', 'titleFr' => 'Dessins récents']);
        $featured = self::uploadArtwork($client, 'A fern', 'Une fougère');
        self::uploadArtwork($client, 'A moss', '');
        $client->jsonRequest('PUT', "/api/admin/artworks/{$featured}/featured");

        $crawler = $client->request('GET', '/beta/en/about');

        self::assertResponseIsSuccessful();
        $data = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
        self::assertSame(['about', 'About the studio', ['A tiny studio.', 'Order on [Etsy](https://www.etsy.com/shop/mossyleafstudio).']], [$data['page'], $data['title'], $data['paragraphs']]);
        self::assertSame('Recent drawings', Json::string($data, 'gallery', 'title'));
        self::assertSame(['A moss'], array_map(static fn (mixed $artwork): string => Json::string($artwork, 'alt'), Json::array($data, 'gallery', 'artworks')));
        self::assertSame('About the studio', $crawler->filter('#shop main h1')->text());
        self::assertSame('https://www.etsy.com/shop/mossyleafstudio', $crawler->filter('#shop main p a')->attr('href'));
        self::assertSame('About the studio · mossyleaf.studio', $crawler->filter('title')->text());

        $french = Json::array(Json::decode($client->request('GET', '/beta/fr/about')->filter('#site-content')->text()), 'data');
        self::assertSame(['Le studio', ['Un petit studio.', 'Il dessine.'], 'Dessins récents', 'A moss'], [$french['title'], $french['paragraphs'], Json::string($french, 'gallery', 'title'), Json::string($french, 'gallery', 'artworks', 0, 'alt')]);
    }

    public function testThePrerenderOfTheHomeSaysWhatThePropsSay(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About the studio', 'text' => "A tiny studio.\n\nIt draws ferns.", 'titleFr' => 'Le studio', 'textFr' => 'Un petit studio.']);
        $client->jsonRequest('PUT', '/api/admin/texts/shop-notes', ['announcement' => 'Free shipping', 'announcementFr' => 'Livraison offerte', 'priceNote' => '', 'priceNoteFr' => '']);
        $client->jsonRequest('POST', '/api/admin/links', ['title' => 'Instagram', 'description' => 'New drawings', 'url' => 'https://www.instagram.com/mossyleaf.studio/', 'tape' => 'leaf']);
        self::uploadArtwork($client, 'A fern', 'Une fougère');

        $crawler = $client->request('GET', '/beta/fr/');
        $props = Json::decode($crawler->filter('#site-content')->text());
        $layout = Json::array($props, 'layout');
        $data = Json::array($props, 'data');
        $shop = $crawler->filter('#shop');

        self::assertSame('home', $shop->attr('data-page'));
        self::assertLayoutMatches($shop, $layout);
        self::assertSame(Json::string($data, 'intro'), $shop->filter('main h1')->text());
        self::assertSame(Json::string($data, 'about', 'title'), $shop->filter('main section h2')->text());
        self::assertSame(Json::string($data, 'about', 'text'), $shop->filter('main section p')->first()->text());
        self::assertSame([Json::string($data, 'about', 'url')], $shop->filter('main section p a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
    }

    public function testThePrerenderOfTheMenuListsTheCategoriesAndCollectionsThePropsCarry(): void
    {
        $client = self::signedInClient();
        $ids = self::push([self::product(1, 'Prints', 'Fox', 1500, 5, self::collection(1, 'Forest')), self::product(2, 'Stickers', 'Moth', 300), self::product(3, 'Cards', 'Bee', 500, 5, self::collection(2, 'Autumn'))]);
        foreach (['prints-fox', 'cards-bee'] as $slug) {
            self::show($client, $ids[$slug]);
        }

        foreach (['/beta/fr/category/cards', '/beta/en/shop/prints-fox', '/beta/en/collection/forest', '/beta/en/about', '/beta/fr/no-such-page'] as $url) {
            $crawler = $client->request('GET', $url);
            $layout = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout');

            self::assertLayoutMatches($crawler->filter('#shop'), $layout);
            self::assertSame(['categories', 3], [Json::string($layout, 'navigation', 1, 'key'), \count(Json::array($layout, 'navigation', 1, 'children'))], $url);
            self::assertSame(['collections', 2, true], [Json::string($layout, 'navigation', 2, 'key'), \count(Json::array($layout, 'navigation', 2, 'children')), Json::at($layout, 'navigation', 2, 'children', 0, 'hidden')], $url);
        }
    }

    public function testThePrerenderOfTheAboutPageSaysWhatThePropsSay(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/about', ['title' => 'About the studio', 'text' => "A tiny studio.\n\nOrder on [Etsy](https://www.etsy.com/shop/mossyleafstudio).", 'titleFr' => '', 'textFr' => '']);
        $featured = self::uploadArtwork($client, 'A fern', '');
        self::uploadArtwork($client, 'A moss', '');
        $client->jsonRequest('PUT', "/api/admin/artworks/{$featured}/featured");

        $crawler = $client->request('GET', '/beta/en/about');
        $props = Json::decode($crawler->filter('#site-content')->text());
        $data = Json::array($props, 'data');
        $shop = $crawler->filter('#shop');

        self::assertLayoutMatches($shop, Json::array($props, 'layout'));
        self::assertSame(Json::string($data, 'title'), $shop->filter('main h1')->text());
        self::assertSame(['A tiny studio.', 'Order on Etsy.'], $shop->filter('main [data-paragraphs] p')->each(static fn (Crawler $paragraph): string => $paragraph->text()));
        self::assertSame([['Etsy', 'https://www.etsy.com/shop/mossyleafstudio']], self::links($shop->filter('main [data-paragraphs] a')));
        self::assertSame([Json::string($data, 'featured', 'url')], $shop->filter('main figure img')->each(static fn (Crawler $image): string => (string) $image->attr('src')));
        self::assertSame(Json::string($data, 'gallery', 'title'), $shop->filter('main section h2')->text());
        self::assertSame(
            array_map(static fn (mixed $artwork): array => [Json::string($artwork, 'url'), Json::string($artwork, 'alt')], Json::array($data, 'gallery', 'artworks')),
            $shop->filter('main section li img')->each(static fn (Crawler $image): array => [(string) $image->attr('src'), (string) $image->attr('alt')]),
        );
    }

    public function testThePrerenderOfTheNotFoundPageLeadsHome(): void
    {
        $crawler = self::signedInClient()->request('GET', '/beta/en/no-such-page');
        $layout = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout');

        self::assertSame([Json::string($layout, 'navigation', 0, 'url')], $crawler->filter('#shop main p a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
    }

    /** @param array<mixed> $layout */
    private static function assertLayoutMatches(Crawler $shop, array $layout): void
    {
        $navigation = Json::array($layout, 'navigation');

        self::assertSame(array_filter([Json::string($layout, 'announcement')]), $shop->filter('[data-announcement]')->each(static fn (Crawler $bar): string => $bar->text()));
        self::assertSame(Json::string($layout, 'navigation', 0, 'url'), $shop->filter('header a[data-home]')->attr('href'));
        self::assertSame(Json::string($layout, 'studioName'), $shop->filter('header a[data-home]')->text());
        self::assertSame(array_map(static fn (mixed $entry): string => Json::string($entry, 'url'), $navigation), $shop->filter('nav[data-navigation] > ul > li > a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame(
            array_map(static fn (mixed $entry): string => true === Json::at($entry, 'current') ? (\is_array($entry) && \array_key_exists('children', $entry) ? 'true' : 'page') : '', $navigation),
            $shop->filter('nav[data-navigation] > ul > li > a')->each(static fn (Crawler $link): string => (string) $link->attr('aria-current')),
        );
        foreach (['categories', 'collections'] as $key) {
            $entry = array_find($navigation, static fn (mixed $entry): bool => $key === Json::at($entry, 'key'));
            $children = null === $entry ? [] : Json::array($entry, 'children');
            self::assertSame(null === $entry, [] === $children, $key);
            self::assertSame(
                array_map(static fn (mixed $child): array => [Json::string($child, 'url'), true === Json::at($child, 'current') ? 'page' : '', true === (Json::array($child)['hidden'] ?? false)], $children),
                $shop->filter("nav[data-navigation] > ul > li > a[data-menu=\"{$key}\"] + #menu-{$key} > ul[data-{$key}] > li > a")->each(static fn (Crawler $link): array => [(string) $link->attr('href'), (string) $link->attr('aria-current'), 1 === $link->filter('[data-label]')->count()]),
                $key,
            );
        }
        self::assertSame(Json::string($layout, 'cartUrl'), $shop->filter('header a[data-cart]')->attr('href'));
        self::assertSame(
            array_map(static fn (mixed $language): string => Json::string($language, 'url'), Json::array($layout, 'languages')),
            $shop->filter('header nav[data-languages] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
        );
        self::assertSame(
            array_map(static fn (mixed $link): array => [Json::string($link, 'title'), Json::string($link, 'url')], Json::array($layout, 'footer', 'links')),
            self::links($shop->filter('footer nav[data-links] a')),
        );
        self::assertSame(Json::string($layout, 'intro'), $shop->filter('footer p')->first()->text());
    }

    /** @return list<array{string, string}> */
    private static function links(Crawler $links): array
    {
        return $links->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]);
    }

    public function testAnUnknownShopAddressShowsTheShopNotFoundPageInItsLanguage(): void
    {
        $client = self::signedInClient();

        foreach (['/beta/fr/no-such-page' => ['fr', 'Page introuvable'], '/beta/en/deep/no-such-page' => ['en', 'Page not found']] as $url => [$locale, $title]) {
            $crawler = $client->request('GET', $url, server: ['HTTP_ACCEPT_LANGUAGE' => 'fr' === $locale ? 'en' : 'fr']);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
            self::assertSame($locale, $crawler->filter('html')->attr('lang'));
            self::assertSame('not-found', Json::string(Json::decode($crawler->filter('#site-content')->text()), 'data', 'page'));
            self::assertSame($title, $crawler->filter('#shop main h1')->text());
            self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
            self::assertSame('noindex, nofollow', $crawler->filter('meta[name="robots"]')->attr('content'));
        }
    }

    public function testTheNotePagesAreUntouched(): void
    {
        $client = self::signedInClient();
        $snapshots = self::getContainer()->get(SiteSnapshots::class);
        $expected = $snapshots->encode(self::getContainer()->get(ContentQueries::class)->site());

        foreach (['/', '/beta/note/'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseStatusCodeSame(200, $url);
            self::assertSame('note', $crawler->filter('#site')->attr('data-page'), $url);
            self::assertSame($expected, $crawler->filter('#site-content')->text(), $url);
            self::assertSame('en', $crawler->filter('html')->attr('lang'), $url);
            self::assertCount(0, $crawler->filter('meta[name^="turbo-"], [data-turbo-track], [data-turbo-permanent]'), $url);
        }
    }

    private static function uploadArtwork(KernelBrowser $client, string $alt, string $altFr): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'artwork');
        self::assertIsString($copy);
        copy(\dirname(__DIR__, 2).'/Fixtures/large-fern.png', $copy);
        $client->request('POST', '/api/admin/artworks', ['alt' => $alt], ['image' => new UploadedFile($copy, 'fern.png', 'image/png', test: true)], ['HTTP_ACCEPT' => 'application/json']);
        $id = Json::string(Json::decode((string) $client->getResponse()->getContent()), 'id');
        $client->jsonRequest('PUT', '/api/admin/artworks/'.$id, ['alt' => $alt, 'altFr' => $altFr]);

        return $id;
    }
}
