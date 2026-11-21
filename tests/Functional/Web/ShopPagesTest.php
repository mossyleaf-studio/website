<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Content\ContentQueries;
use App\Application\Content\SiteSnapshots;
use App\Presentation\Web\Shop\ShopNotFoundListener;
use App\Presentation\Web\Shop\SiteLabels;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ShopPagesTest extends WebTestCase
{
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testTheShopStaysClosedToVisitorsWithoutStartingASession(): void
    {
        $client = self::createClient();

        foreach (['/fr/', '/en/', '/en/about', '/fr/about', '/fr/no-such-page', '/fr/?preview=1', '/fr/about?preview=true', '/fr/x?preview=1'] as $url) {
            $client->request('GET', $url, server: ['HTTP_PREVIEW' => '1']);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testThePreviewNeedsSignIn(): void
    {
        $client = self::createClient();

        foreach (['/beta/fr/', '/beta/en/about', '/beta/fr/no-such-page'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/login', null, $url);
        }
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

        $props = Json::decode($crawler->filter('#site-content')->text());
        self::assertSame(['layout', 'data'], array_keys($props));
        self::assertSame('fr', Json::string($props, 'layout', 'locale'));
        self::assertTrue(Json::at($props, 'layout', 'preview'));
        self::assertSame('Livraison offerte', Json::string($props, 'layout', 'announcement'));
        self::assertSame('home', Json::string($props, 'data', 'page'));
        self::assertSame('Petites bêtes dessinées.', Json::string($props, 'data', 'intro'));
        self::assertSame('Une fougère', Json::string($props, 'data', 'featured', 'alt'));
        self::assertSame(['title' => 'Le studio', 'text' => 'A tiny studio.', 'url' => '/beta/fr/about'], Json::array($props, 'data', 'about'));
        self::assertSame([['key' => 'home', 'url' => '/beta/fr/', 'current' => true], ['key' => 'shop', 'url' => '/beta/fr/shop', 'current' => false], ['key' => 'conventions', 'url' => '/beta/fr/conventions', 'current' => false], ['key' => 'about', 'url' => '/beta/fr/about', 'current' => false], ['key' => 'contact', 'url' => '/beta/fr/contact', 'current' => false]], Json::array($props, 'layout', 'navigation'));
        self::assertSame([['locale' => 'en', 'url' => '/beta/en/', 'current' => false], ['locale' => 'fr', 'url' => '/beta/fr/', 'current' => true]], Json::array($props, 'layout', 'languages'));

        self::assertSame('Petites bêtes dessinées.', $crawler->filter('#shop main h1')->text());
        self::assertSame('A tiny studio.', $crawler->filter('#shop main section p')->first()->text());
        self::assertSame('Livraison offerte', $crawler->filter('#shop [data-announcement]')->text());
        self::assertSame(['Boutique', 'Conventions', 'À propos', 'Contact'], $crawler->filter('#shop nav[data-navigation] a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame('Aller au contenu', $crawler->filter('#shop a[href="#main"]')->text());
        self::assertSame('Aperçu du brouillon', $crawler->filter('#shop aside[data-draft]')->attr('aria-label'));
        self::assertSame('Brouillon Seuls les admins voient cette page. Les textes, liens, images et pages légales attendent « Publier » ; les produits, conventions et frais de port sont déjà en ligne.', $crawler->filter('#shop aside[data-draft] p')->text());
        self::assertSame(['shop' => '/beta/fr/', 'note' => '/beta/note/', 'admin' => '/admin'], Json::array($props, 'layout', 'draft'));
        self::assertSame([['Boutique', '/beta/fr/'], ['Note « en construction »', '/beta/note/'], ['Retour à l’admin', '/admin']], self::links($crawler->filter('#shop aside[data-draft] a')));
        self::assertSame(['page', null, null], $crawler->filter('#shop aside[data-draft] a')->each(static fn (Crawler $link): ?string => $link->attr('aria-current')));
        self::assertSame('Aperçu du brouillon', $crawler->filter('#shop aside[data-draft] nav')->attr('aria-label'));
        self::assertSame(['id', 'title', 'url'], array_keys(Json::array($props, 'layout', 'footer', 'links', 0)));
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

    public function testTheLanguageLinkLeadsToTheSamePageInTheOtherLanguage(): void
    {
        $client = self::signedInClient();

        foreach (['/beta/fr/' => '/beta/en/', '/beta/en/' => '/beta/fr/', '/beta/en/about' => '/beta/fr/about', '/beta/fr/no-such-page' => '/beta/en/no-such-page'] as $url => $other) {
            $crawler = $client->request('GET', $url);

            self::assertSame($other, $crawler->filter('#shop [data-languages] a[hreflang]:not([aria-current])')->attr('href'), $url);
            self::assertSame($url, $crawler->filter('#shop [data-languages] a[aria-current]')->attr('href'), $url);
        }
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
        self::assertSame(['page', 'title', 'paragraphs', 'featured', 'gallery'], array_keys($data));
        self::assertSame(['about', 'About the studio', ['A tiny studio.', 'Order on [Etsy](https://www.etsy.com/shop/mossyleafstudio).']], [$data['page'], $data['title'], $data['paragraphs']]);
        self::assertSame(['id', 'url', 'alt', 'width', 'height'], array_keys(Json::array($data, 'featured')));
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
        self::assertSame([[self::label('fr', 'home.aboutLink'), Json::string($data, 'about', 'url')]], self::links($shop->filter('main section p a')));
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

        self::assertSame([[self::label('en', 'notFound.home'), Json::string($layout, 'navigation', 0, 'url')]], self::links($crawler->filter('#shop main p a')));
        self::assertSame(self::label('en', 'notFound.text'), $crawler->filter('#shop main p')->first()->text());
    }

    /** @param array<mixed> $layout */
    private static function assertLayoutMatches(Crawler $shop, array $layout): void
    {
        $locale = Json::string($layout, 'locale');
        $navigation = array_values(array_filter(Json::array($layout, 'navigation'), static fn (mixed $entry): bool => 'home' !== Json::string($entry, 'key')));

        self::assertSame(self::label($locale, 'layout.skip'), $shop->filter('a[href="#main"]')->text());
        self::assertSame(array_filter([Json::string($layout, 'announcement')]), $shop->filter('[data-announcement]')->each(static fn (Crawler $bar): string => $bar->text()));
        self::assertSame(Json::string($layout, 'navigation', 0, 'url'), $shop->filter('header a[data-home]')->attr('href'));
        self::assertSame(Json::string($layout, 'studioName'), $shop->filter('header a[data-home]')->text());
        self::assertSame(array_map(static fn (mixed $entry): array => [self::label($locale, 'nav.'.Json::string($entry, 'key')), Json::string($entry, 'url')], $navigation), self::links($shop->filter('nav[data-navigation] a')));
        self::assertSame(self::label($locale, 'layout.navigation'), $shop->filter('nav[data-navigation]')->attr('aria-label'));
        self::assertSame([[self::label($locale, 'cart.link'), Json::string($layout, 'cartUrl')]], self::links($shop->filter('header a[data-cart]')));
        self::assertSame(self::label($locale, 'layout.languages'), $shop->filter('header nav[data-languages]')->attr('aria-label'));
        self::assertCount(1, $shop->filter('nav[data-languages]'));
        self::assertSame(self::links($shop->filter('header [data-languages] a')), self::links($shop->filter('footer [data-languages] a')));
        self::assertSame(
            array_map(static fn (mixed $language): array => [self::label($locale, 'language.code.'.Json::string($language, 'locale')).' '.self::label($locale, 'language.name.'.Json::string($language, 'locale')), Json::string($language, 'url')], Json::array($layout, 'languages')),
            self::links($shop->filter('header nav[data-languages] a')),
        );
        self::assertSame(
            array_map(static fn (mixed $link): array => [Json::string($link, 'title'), Json::string($link, 'url')], Json::array($layout, 'footer', 'links')),
            self::links($shop->filter('footer nav[data-links] a')),
        );
        self::assertSame(
            array_map(static fn (mixed $link): string => str_contains(Json::string($link, 'url'), 'instagram.com') ? 'me' : '', Json::array($layout, 'footer', 'links')),
            $shop->filter('footer nav[data-links] a')->each(static fn (Crawler $link): string => (string) $link->attr('rel')),
        );
        self::assertSame(Json::string($layout, 'intro'), $shop->filter('footer p')->first()->text());
        self::assertSame(self::label($locale, 'footer.copyright', ['year' => date('Y'), 'studio' => Json::string($layout, 'studioName')]), $shop->filter('footer p')->last()->text());
    }

    /** @return list<array{string, string}> */
    private static function links(Crawler $links): array
    {
        return $links->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]);
    }

    /** @param array<string, scalar> $parameters */
    private static function label(string $locale, string $key, array $parameters = []): string
    {
        $label = self::getContainer()->get(SiteLabels::class)->label($locale, $key, $parameters);
        self::assertNotSame($key, $label, "{$locale} {$key}");

        return $label;
    }

    public function testAShopAddressWithoutTheTrailingSlashRedirects(): void
    {
        self::createClient()->request('GET', '/fr');
        self::assertResponseRedirects('http://localhost/fr/', 301);
        self::ensureKernelShutdown();

        self::signedInClient()->request('GET', '/beta/en');
        self::assertResponseRedirects('http://localhost/beta/en/', 301);
    }

    public function testAnUnknownLanguageIsNotFound(): void
    {
        self::createClient()->request('GET', '/de/');
        self::assertResponseStatusCodeSame(404);
        self::ensureKernelShutdown();

        self::signedInClient()->request('GET', '/beta/de/');
        self::assertResponseStatusCodeSame(404);
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

    public function testANotFoundRaisedOnAClosedShopAddressRedirectsHome(): void
    {
        $kernel = self::bootKernel();
        $request = Request::create('/fr/about');
        $request->attributes->add(['_route' => 'shop_about', '_route_params' => ['_locale' => 'fr'], '_locale' => 'fr']);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new NotFoundHttpException());

        self::getContainer()->get(ShopNotFoundListener::class)($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/', $response->getTargetUrl());
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

    public function testEveryShopDocumentCarriesWhatTurboNeedsToSwapItWithoutAReload(): void
    {
        $client = self::signedInClient();

        foreach (['/beta/fr/', '/beta/en/about', '/beta/fr/no-such-page'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertSame('no-preview', $crawler->filter('head meta[name="turbo-cache-control"]')->attr('content'), $url);
            $entry = $crawler->filter('head script[type="module"][src*="/assets/shop.js"]');
            self::assertCount(1, $entry, $url);
            self::assertSame('reload', $entry->attr('data-turbo-track'), $url);
            self::assertCount(0, $crawler->filter('head script[type="application/ld+json"]'), $url);
            $announcer = $crawler->filter('body > #shop-announcer');
            self::assertCount(1, $announcer, $url);
            self::assertSame('', $announcer->attr('data-turbo-permanent'), $url);
            self::assertSame('polite', $announcer->attr('aria-live'), $url);
            self::assertSame('', $announcer->text(), $url);
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
