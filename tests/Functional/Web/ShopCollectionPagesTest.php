<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\CollectionView;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Functional\Content\OpensTheShop;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class ShopCollectionPagesTest extends WebTestCase
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

    public function testACollectionPageListsItsProductsAcrossCategories(): void
    {
        $client = self::signedInClient();
        self::stockCollections($client);

        $crawler = $client->request('GET', '/beta/fr/collection/forest-critters');

        self::assertResponseIsSuccessful();
        $data = self::data($crawler);
        self::assertSame(['page', 'title', 'collection', 'filters', 'products'], array_keys($data));
        self::assertSame(['collection', 'Bestioles de la forêt', ['key' => 'forest-critters', 'label' => 'Bestioles de la forêt']], [$data['page'], $data['title'], $data['collection']]);
        self::assertSame([['/beta/fr/shop/prints-fox', false], ['/beta/fr/shop/prints-owl', false], ['/beta/fr/shop/stickers-moth', true], ['/beta/fr/shop/stickers-newt', false]], self::cards($data));
        self::assertSame(['kind' => 'category', 'items' => [
            ['key' => null, 'label' => 'Tout', 'url' => '/beta/fr/collection/forest-critters', 'current' => true],
            ['key' => 'prints', 'label' => 'Prints', 'url' => '/beta/fr/collection/forest-critters?category=prints', 'current' => false],
            ['key' => 'stickers', 'label' => 'Stickers', 'url' => '/beta/fr/collection/forest-critters?category=stickers', 'current' => false],
        ]], $data['filters']);
        self::assertSame('Bestioles de la forêt · mossyleaf.studio', $crawler->filter('title')->text());

        $main = $crawler->filter('#shop main');
        self::assertSame('Bestioles de la forêt', $main->filter('h1')->text());
        self::assertSame(['/beta/fr/shop/prints-fox', '/beta/fr/shop/prints-owl', '/beta/fr/shop/stickers-moth', '/beta/fr/shop/stickers-newt'], $main->filter('[data-products] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame('Filtrer par catégorie', $main->filter('nav[data-filters]')->attr('aria-label'));
        self::assertSame(self::chips(Json::array($data, 'filters')), self::prerenderedChips($main));
    }

    public function testVisitorsSeeAShownCollectionWithItsVisibleProductsOnly(): void
    {
        $client = self::openShop();

        $crawler = $client->request('GET', '/en/collection/forest-critters');

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Set-Cookie');
        $data = self::data($crawler);
        self::assertSame(['Forest critters', ['key' => 'forest-critters', 'label' => 'Forest critters']], [$data['title'], $data['collection']]);
        self::assertSame(['/en/shop/prints-fox', '/en/shop/prints-owl', '/en/shop/stickers-newt'], array_column(self::cards($data), 0));
        self::assertSame('index, follow, max-image-preview:large', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('http://localhost/en/collection/forest-critters', $crawler->filter('link[rel="canonical"]')->attr('href'));
        $etag = (string) $client->getResponse()->getEtag();
        self::assertSame('"'.hash('xxh128', (string) $client->getResponse()->getContent()).'"', $etag);
        $client->request('GET', '/en/collection/forest-critters', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
    }

    public function testAnUnknownHiddenOrEmptyCollectionAnswersTheShopNotFoundPage(): void
    {
        $client = self::openShop();

        foreach (['/en/collection/nope', '/en/collection/autumn-market', '/fr/collection/moonlight', '/en/collection/winter', '/en/collection/Forest-critters', '/en/collection/forest-critters%00x'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertSame('not-found', $crawler->filter('#shop')->attr('data-page'), $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testTheCollectionPagesStayClosedWhileTheShopIsClosed(): void
    {
        $client = self::signedInClient();
        self::stockCollections($client);
        $client->getCookieJar()->clear();

        foreach (['/en/collection/forest-critters', '/fr/collection/nope', '/en/category/prints?collection=forest-critters'] as $url) {
            $client->request('GET', $url);

            self::assertResponseRedirects('/', 302, $url);
            self::assertResponseNotHasHeader('Set-Cookie', $url);
        }
    }

    public function testThePreviewShowsACollectionNotShownYetFlaggedHidden(): void
    {
        $client = self::signedInClient();
        self::stockCollections($client);

        $crawler = $client->request('GET', '/beta/en/collection/autumn-market');

        self::assertResponseIsSuccessful();
        $data = self::data($crawler);
        self::assertSame(['key' => 'autumn-market', 'label' => 'Autumn market', 'hidden' => true], $data['collection']);
        self::assertSame([['/beta/en/shop/cards-bee', false]], self::cards($data));
        self::assertSame('Hidden', $crawler->filter('#shop main [data-hidden] [data-label]')->text());

        self::assertSame([
            ['key' => 'forest-critters', 'label' => 'Forest critters', 'url' => '/beta/en/collection/forest-critters', 'current' => false],
            ['key' => 'autumn-market', 'label' => 'Autumn market', 'url' => '/beta/en/collection/autumn-market', 'hidden' => true, 'current' => true],
            ['key' => 'moonlight', 'label' => 'Moonlight', 'url' => '/beta/en/collection/moonlight', 'current' => false],
        ], Json::array(self::shopEntry($crawler), 'collections'));
        self::assertSame([['/beta/en/shop/cards-lynx', true]], self::cards(self::data($client->request('GET', '/beta/en/collection/moonlight'))));
        $client->request('GET', '/beta/en/collection/winter');
        self::assertResponseStatusCodeSame(404);
    }

    public function testACategoryPageOffersTheCollectionsFoundInIt(): void
    {
        $client = self::signedInClient();
        self::stockCollections($client);

        $prints = self::data($client->request('GET', '/beta/en/category/prints'));
        self::assertSame(['page', 'title', 'category', 'filters', 'products'], array_keys($prints));
        self::assertSame(['kind' => 'collection', 'items' => [
            ['key' => null, 'label' => 'All', 'url' => '/beta/en/category/prints', 'current' => true],
            ['key' => 'forest-critters', 'label' => 'Forest critters', 'url' => '/beta/en/category/prints?collection=forest-critters', 'current' => false],
        ]], $prints['filters']);

        $crawler = $client->request('GET', '/beta/en/category/cards');
        $cards = self::data($crawler);
        self::assertSame([
            [null, 'All', true, false],
            ['autumn-market', 'Autumn market', false, true],
            ['moonlight', 'Moonlight', false, false],
        ], array_map(static fn (mixed $item): array => [Json::at($item, 'key'), Json::string($item, 'label'), Json::at($item, 'current'), true === (Json::array($item)['hidden'] ?? false)], Json::array($cards, 'filters', 'items')));
        self::assertSame('Filter by collection', $crawler->filter('#shop main nav[data-filters]')->attr('aria-label'));
        self::assertSame(self::chips(Json::array($cards, 'filters')), self::prerenderedChips($crawler->filter('#shop main')));

        $collectionIds = self::collectionIds();
        $client->jsonRequest('PUT', '/api/admin/collections/order', ['ids' => [$collectionIds['Moonlight'], $collectionIds['Forest critters'], $collectionIds['Autumn market'], $collectionIds['Winter']]]);
        self::assertResponseIsSuccessful();
        self::assertSame([null, 'moonlight', 'autumn-market'], array_map(static fn (mixed $item): mixed => Json::at($item, 'key'), Json::array(self::data($client->request('GET', '/beta/en/category/cards')), 'filters', 'items')));
        self::assertSame(['moonlight', 'forest-critters', 'autumn-market'], array_map(static fn (mixed $item): string => Json::string($item, 'key'), Json::array(self::shopEntry($client->getCrawler()), 'collections')));
    }

    public function testACategoryWithoutCollectionsHasNoFilters(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);

        $crawler = $client->request('GET', '/beta/en/category/prints');

        self::assertArrayNotHasKey('filters', self::data($crawler));
        self::assertCount(0, $crawler->filter('#shop main nav[data-filters]'));
        self::assertSame([], Json::array(self::shopEntry($crawler), 'collections'));
        self::assertCount(0, $crawler->filter('#shop [data-collections]'));
    }

    public function testNoChipRowWhenTheOnlyChoiceHoldsEveryProductOfThePage(): void
    {
        $client = self::openShop();

        foreach (['/en/category/stickers', '/en/category/stickers?collection=forest-critters'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            self::assertArrayNotHasKey('filters', self::data($crawler), $url);
            self::assertSame(['/en/shop/stickers-newt'], array_column(self::cards(self::data($crawler)), 0), $url);
            self::assertCount(0, $crawler->filter('#shop main nav[data-filters]'), $url);
        }

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        foreach (['/beta/en/collection/autumn-market', '/beta/en/collection/moonlight', '/beta/en/category/stickers'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            self::assertArrayNotHasKey('filters', self::data($crawler), $url);
            self::assertCount(0, $crawler->filter('#shop main nav[data-filters]'), $url);
        }
    }

    public function testVisitorsAreOfferedOnlyTheShownCollections(): void
    {
        $client = self::openShop();

        $prints = self::data($client->request('GET', '/en/category/prints'));
        self::assertSame([[null, 'All', true], ['forest-critters', 'Forest critters', false]], array_map(static fn (mixed $item): array => [Json::at($item, 'key'), Json::string($item, 'label'), Json::at($item, 'current')], Json::array($prints, 'filters', 'items')));
        self::assertArrayNotHasKey('filters', self::data($client->request('GET', '/en/category/cards')));
    }

    public function testAFilteredPageNarrowsTheProductsAndKeepsTheAddressesOfThePage(): void
    {
        $client = self::openShop();

        $crawler = $client->request('GET', '/en/category/prints?collection=forest-critters');

        self::assertResponseIsSuccessful();
        $data = self::data($crawler);
        self::assertSame('Prints', $data['title']);
        self::assertSame(['/en/shop/prints-fox', '/en/shop/prints-owl'], array_column(self::cards($data), 0));
        self::assertSame([false, true], array_map(static fn (mixed $item): mixed => Json::at($item, 'current'), Json::array($data, 'filters', 'items')));
        self::assertSame(['/en/category/prints'], $crawler->filter('#shop main nav[data-filters] a:not([aria-current])')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame(['/en/category/prints?collection=forest-critters'], $crawler->filter('#shop main nav[data-filters] a[aria-current="true"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame('Prints · mossyleaf.studio', $crawler->filter('title')->text());
        self::assertSame('http://localhost/en/category/prints', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame(['en' => 'http://localhost/en/category/prints', 'fr' => 'http://localhost/fr/category/prints', 'x-default' => 'http://localhost/en/category/prints'], self::alternates($crawler));
        self::assertSame(['/en/category/prints?collection=forest-critters', '/fr/category/prints?collection=forest-critters'], array_map(static fn (mixed $language): string => Json::string($language, 'url'), Json::array(self::props($crawler), 'layout', 'languages')));
        self::assertSame(['/en/category/prints?collection=forest-critters', '/fr/category/prints?collection=forest-critters'], $crawler->filter('header nav[data-languages] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));

        $crawler = $client->request('GET', '/fr/collection/forest-critters?category=stickers');

        self::assertResponseIsSuccessful();
        $data = self::data($crawler);
        self::assertSame('Bestioles de la forêt', $data['title']);
        self::assertSame(['/fr/shop/stickers-newt'], array_column(self::cards($data), 0));
        self::assertSame([[null, false], ['prints', false], ['stickers', true]], array_map(static fn (mixed $item): array => [Json::at($item, 'key'), Json::at($item, 'current')], Json::array($data, 'filters', 'items')));
        self::assertSame('http://localhost/fr/collection/forest-critters', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame(['/en/collection/forest-critters?category=stickers', '/fr/collection/forest-critters?category=stickers'], array_map(static fn (mixed $language): string => Json::string($language, 'url'), Json::array(self::props($crawler), 'layout', 'languages')));
        self::assertSame(self::chips(Json::array($data, 'filters')), self::prerenderedChips($crawler->filter('#shop main')));
    }

    public function testAFilterValueThatIsUnknownHiddenOrMatchesNothingAnswersTheShopNotFoundPage(): void
    {
        $client = self::openShop();

        foreach ([
            '/en/category/prints?collection=nope',
            '/en/category/cards?collection=autumn-market',
            '/en/category/cards?collection=moonlight',
            '/en/category/prints?collection=moonlight',
            '/en/category/prints?collection[]=forest-critters',
            '/en/category/prints?collection=',
            '/en/collection/forest-critters?category=cards',
            '/en/collection/forest-critters?category=nope',
            '/fr/collection/forest-critters?category=Prints',
        ] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertSame('not-found', $crawler->filter('#shop')->attr('data-page'), $url);
        }

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $client->request('GET', '/beta/en/category/cards?collection=autumn-market');
        self::assertResponseIsSuccessful();
        self::assertSame(['/beta/en/shop/cards-bee'], array_column(self::cards(self::data($client->getCrawler())), 0));
    }

    public function testTheMenuListsTheCollectionsTheVisitorMaySee(): void
    {
        $client = self::openShop();

        $crawler = $client->request('GET', '/en/about');
        $entry = self::shopEntry($crawler);
        self::assertSame([['key' => 'forest-critters', 'label' => 'Forest critters', 'url' => '/en/collection/forest-critters', 'current' => false]], $entry['collections']);
        self::assertSame('Collections', $crawler->filter('#'.$crawler->filter('#shop nav[data-navigation] ul[data-collections]')->attr('aria-labelledby'))->text());
        self::assertSame([['Forest critters', '/en/collection/forest-critters', '']], self::prerenderedCollections($crawler));
        self::assertCount(\count(Json::array($entry, 'children')), $crawler->filter('#shop nav[data-navigation] ul[data-categories] > li'));
        self::assertCount(0, $crawler->filter('#shop nav[data-navigation] [data-categories] [data-collections]'));

        $crawler = $client->request('GET', '/en/collection/forest-critters');
        $entry = self::shopEntry($crawler);
        self::assertTrue($entry['current']);
        self::assertSame([true], array_map(static fn (mixed $collection): mixed => Json::at($collection, 'current'), Json::array($entry, 'collections')));
        self::assertSame([], array_filter(Json::array($entry, 'children'), static fn (mixed $category): bool => true === Json::at($category, 'current')));
        self::assertSame([['Forest critters', '/en/collection/forest-critters', 'page']], self::prerenderedCollections($crawler));
        self::assertSame(['/en/shop'], $crawler->filter('nav[data-navigation] a[aria-current="true"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')));

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $client->jsonRequest('DELETE', '/api/admin/collections/'.self::collectionIds()['Forest critters'].'/visibility');
        self::assertResponseIsSuccessful();
        $client->getCookieJar()->clear();

        $crawler = $client->request('GET', '/en/category/prints');
        self::assertSame([], Json::array(self::shopEntry($crawler), 'collections'));
        self::assertCount(0, $crawler->filter('#shop [data-collections]'));
        self::assertArrayNotHasKey('filters', self::data($crawler));
    }

    public function testThePrerenderedMenuOfThePreviewMarksTheHiddenCollections(): void
    {
        $client = self::signedInClient();
        self::stockCollections($client);

        $crawler = $client->request('GET', '/beta/fr/');

        self::assertSame(
            array_map(static fn (mixed $collection): array => [Json::string($collection, 'label'), Json::string($collection, 'url'), true === (Json::array($collection)['hidden'] ?? false)], Json::array(self::shopEntry($crawler), 'collections')),
            $crawler->filter('#shop nav[data-navigation] ul[data-collections] a')->each(static fn (Crawler $link): array => [$link->filter('[data-collection-label]')->text(), (string) $link->attr('href'), 1 === $link->filter('[data-label]')->count()]),
        );
        self::assertSame('Bestioles de la forêt', $crawler->filter('#shop ul[data-collections] [data-collection-label]')->first()->text());
        self::assertSame('Masqué', $crawler->filter('#shop ul[data-collections] [data-label]')->text());
    }

    public function testTheProductPageNamesItsCollectionWhenTheVisitorMaySeeIt(): void
    {
        $client = self::openShop();

        $crawler = $client->request('GET', '/en/shop/prints-fox');
        $data = self::data($crawler);
        self::assertSame(['page', 'title', 'description', 'price', 'priceNote', 'category', 'collection', 'photos', 'variants', 'soldOut', 'related'], array_keys($data));
        self::assertSame(['key' => 'forest-critters', 'label' => 'Forest critters', 'url' => '/en/collection/forest-critters'], $data['collection']);
        self::assertSame([['Forest critters', '/en/collection/forest-critters']], $crawler->filter('#shop main [data-product-collection] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        self::assertSame('Collection: Forest critters', $crawler->filter('#shop main [data-product-collection]')->text());

        foreach (['/en/shop/cards-bee', '/en/shop/prints-wren'] as $url) {
            $crawler = $client->request('GET', $url);
            self::assertArrayNotHasKey('collection', self::data($crawler), $url);
            self::assertCount(0, $crawler->filter('#shop main [data-product-collection]'), $url);
        }

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $crawler = $client->request('GET', '/beta/fr/shop/cards-bee');
        self::assertSame(['key' => 'autumn-market', 'label' => 'Autumn market', 'url' => '/beta/fr/collection/autumn-market', 'hidden' => true], self::data($crawler)['collection']);
        self::assertSame('/beta/fr/collection/autumn-market', $crawler->filter('#shop main [data-product-collection] a')->attr('href'));
        self::assertSame('Collection : Autumn market Masqué', $crawler->filter('#shop main [data-product-collection]')->text());
        self::assertSame(['key' => 'forest-critters', 'label' => 'Bestioles de la forêt', 'url' => '/beta/fr/collection/forest-critters'], self::data($client->request('GET', '/beta/fr/shop/prints-fox'))['collection']);
    }

    public function testVisitorsNeverLearnOfACollectionThatIsNotShown(): void
    {
        $client = self::openShop();

        foreach (['/en/', '/fr/', '/en/category/prints', '/en/category/cards', '/en/category/stickers', '/en/collection/forest-critters', '/en/shop/prints-fox', '/en/shop/cards-bee', '/en/shop/stickers-newt', '/en/about', '/en/cart', '/en/no-such-page', '/sitemap.xml'] as $url) {
            $client->request('GET', $url);
            $html = (string) $client->getResponse()->getContent();

            self::assertDoesNotMatchRegularExpression('{autumn|moonlight|winter}i', $html, $url);
            if ('/sitemap.xml' !== $url) {
                self::assertFalse(self::hasKey(self::props($client->getCrawler()), 'hidden'), $url);
            }
        }
    }

    public function testTheSitemapListsTheCollectionPagesVisitorsSee(): void
    {
        $client = self::openShop();

        $client->request('GET', '/sitemap.xml');

        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        $urls = [];
        foreach ($sitemap->children(self::SITEMAP)->url as $url) {
            $alternates = [];
            foreach ($url->children(self::XHTML)->link as $link) {
                $attributes = $link->attributes();
                self::assertNotNull($attributes);
                $alternates[(string) $attributes['hreflang']] = (string) $attributes['href'];
            }
            $urls[(string) $url->loc] = $alternates;
        }
        $alternates = ['fr' => 'http://localhost/fr/collection/forest-critters', 'en' => 'http://localhost/en/collection/forest-critters', 'x-default' => 'http://localhost/en/collection/forest-critters'];
        self::assertSame($alternates, $urls['http://localhost/en/collection/forest-critters'] ?? null);
        self::assertSame($alternates, $urls['http://localhost/fr/collection/forest-critters'] ?? null);
        self::assertSame(['http://localhost/en/collection/forest-critters', 'http://localhost/fr/collection/forest-critters'], array_values(array_filter(array_keys($urls), static fn (string $url): bool => str_contains($url, '/collection/'))));
    }

    public function testCollectionKeysAreTheSameInBothLanguagesUniqueAndNeverEmpty(): void
    {
        $client = self::signedInClient();
        $ids = self::push([
            self::product(1, 'Prints', 'Fox', 900, 5, self::collection(1, 'Forest & Moss')),
            self::product(2, 'Prints', 'Owl', 900, 5, self::collection(2, 'forest moss!')),
            self::product(3, 'Prints', 'Moth', 900, 5, self::collection(3, '—')),
        ]);
        foreach ($ids as $id) {
            self::show($client, $id);
        }

        $keys = [];
        foreach (['en', 'fr'] as $locale) {
            $collections = Json::array(self::shopEntry($client->request('GET', "/beta/{$locale}/")), 'collections');
            $keys[$locale] = array_map(static fn (mixed $collection): string => Json::string($collection, 'key'), $collections);
            foreach ($collections as $collection) {
                self::assertCount(1, Json::array(self::data($client->request('GET', Json::string($collection, 'url'))), 'products'), Json::string($collection, 'url'));
            }
        }

        self::assertSame($keys['en'], $keys['fr']);
        self::assertCount(3, array_unique(array_filter($keys['en'])));
        self::assertSame('forest-moss', $keys['en'][0]);
        self::assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $keys['en'][1]);
        self::assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $keys['en'][2]);
    }

    public function testTheCollectionAndCategoryPagesDoNotRunOneQueryPerProductOrPerCollection(): void
    {
        $client = self::signedInClient();
        self::stockForest($client, 2);
        $few = [self::queries($client, '/beta/en/collection/forest-1'), self::queries($client, '/beta/en/category/prints'), self::queries($client, '/beta/en/shop/prints-fern1')];

        self::stockForest($client, 8);

        self::assertSame($few, [self::queries($client, '/beta/en/collection/forest-1'), self::queries($client, '/beta/en/category/prints'), self::queries($client, '/beta/en/shop/prints-fern1')]);
    }

    public function testTheMenuReadsTheCollectionsWithoutLoadingTheProducts(): void
    {
        $client = self::signedInClient();
        self::stockForest($client, 2);
        $client->enableProfiler();

        $client->request('GET', '/beta/en/nowhere');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['forest-1', 'forest-2'], array_map(static fn (mixed $collection): string => Json::string($collection, 'key'), Json::array(self::shopEntry($client->getCrawler()), 'collections')));
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $sql = '';
        foreach ($collector->getQueries() as $queries) {
            foreach (\is_array($queries) ? $queries : [] as $query) {
                $sql .= "\n".Json::string($query, 'sql');
            }
        }
        self::assertStringNotContainsString('product_variant', $sql);
        self::assertStringNotContainsString('product_photo', $sql);
    }

    private static function stockForest(KernelBrowser $client, int $count): void
    {
        $ids = self::push(array_map(static fn (int $number): PushedProduct => self::product($number, 'Prints', 'Fern'.$number, 900, ['A4' => 2, 'A5' => 3], self::collection($number, 'Forest '.$number)), range(1, $count)));
        foreach ($ids as $id) {
            self::show($client, $id);
            self::photo($client, $id);
        }
        foreach (self::collectionIds() as $id) {
            $client->jsonRequest('PUT', "/api/admin/collections/{$id}/visibility");
            self::assertResponseIsSuccessful();
        }
    }

    private static function queries(KernelBrowser $client, string $url): int
    {
        $client->enableProfiler();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful($url);
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /** @return array<string, string> */
    private static function stockCollections(KernelBrowser $client): array
    {
        $forest = self::collection(1, 'Forest critters');
        $ids = self::push([
            self::product(1, 'Prints', 'Fox', 1500, ['A4' => 12, 'A5' => 0], $forest),
            self::product(2, 'Prints', 'Owl', 1200, 0, $forest),
            self::product(3, 'Stickers', 'Moth', 300, 4, $forest),
            self::product(4, 'Cards', 'Bee', 500, 3, self::collection(2, 'Autumn market')),
            self::product(5, 'Prints', 'Wren', 900, 3, null),
            self::product(6, 'Stickers', 'Newt', 300, 3, $forest),
            self::product(7, 'Cards', 'Hare', 0, 3, self::collection(3, 'Winter')),
            self::product(8, 'Cards', 'Lynx', 500, 3, self::collection(4, 'Moonlight')),
        ]);
        foreach (['prints-fox', 'prints-owl', 'cards-bee', 'prints-wren', 'stickers-newt', 'cards-hare'] as $slug) {
            self::show($client, $ids[$slug]);
        }
        $collections = self::collectionIds();
        foreach (['Forest critters', 'Moonlight', 'Winter'] as $name) {
            $client->jsonRequest('PUT', "/api/admin/collections/{$collections[$name]}/visibility");
            self::assertResponseIsSuccessful();
        }
        $client->jsonRequest('PUT', "/api/admin/collections/{$collections['Forest critters']}", ['label' => 'Forest critters', 'labelFr' => 'Bestioles de la forêt']);
        self::assertResponseIsSuccessful();

        return $ids;
    }

    private static function openShop(): KernelBrowser
    {
        $client = self::signedInClient();
        $client->jsonRequest('PUT', '/api/admin/texts/identity', ['studioName' => 'mossyleaf.studio', 'intro' => 'Little critters.', 'metaDescription' => 'Drawings.']);
        self::stockCollections($client);
        self::openTheShop($client);
        $client->getCookieJar()->clear();

        return $client;
    }

    /** @return array<string, string> */
    private static function collectionIds(): array
    {
        return array_column(array_map(static fn (CollectionView $collection): array => [$collection->name, $collection->id], self::getContainer()->get(CatalogueQueries::class)->collections()), 1, 0);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array{string, bool}>
     */
    private static function cards(array $data): array
    {
        return array_values(array_map(static fn (mixed $card): array => [Json::string($card, 'url'), true === (Json::array($card)['hidden'] ?? false)], Json::array($data, 'products')));
    }

    /**
     * @param array<mixed> $filters
     *
     * @return list<array{string, string, string}>
     */
    private static function chips(array $filters): array
    {
        return array_values(array_map(static fn (mixed $item): array => [Json::string($item, 'label'), Json::string($item, 'url'), true === Json::at($item, 'current') ? 'true' : ''], Json::array($filters, 'items')));
    }

    /** @return list<array{string, string, string}> */
    private static function prerenderedChips(Crawler $main): array
    {
        return $main->filter('nav[data-filters] a[data-filter]')->each(static fn (Crawler $link): array => [$link->filter('[data-filter-label]')->text(), (string) $link->attr('href'), (string) $link->attr('aria-current')]);
    }

    /** @return list<array{string, string, string}> */
    private static function prerenderedCollections(Crawler $crawler): array
    {
        return $crawler->filter('#shop nav[data-navigation] ul[data-collections] a')->each(static fn (Crawler $link): array => [$link->filter('[data-collection-label]')->text(), (string) $link->attr('href'), (string) $link->attr('aria-current')]);
    }

    private static function hasKey(mixed $props, string $name): bool
    {
        if (!\is_array($props)) {
            return false;
        }

        foreach ($props as $key => $value) {
            if ($key === $name || self::hasKey($value, $name)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private static function alternates(Crawler $crawler): array
    {
        return array_combine(
            $crawler->filter('link[rel="alternate"][hreflang]')->each(static fn (Crawler $link): string => (string) $link->attr('hreflang')),
            $crawler->filter('link[rel="alternate"][hreflang]')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
        );
    }

    /** @return array<mixed> */
    private static function shopEntry(Crawler $crawler): array
    {
        $entry = array_find(Json::array(self::props($crawler), 'layout', 'navigation'), static fn (mixed $entry): bool => 'shop' === Json::at($entry, 'key'));
        self::assertIsArray($entry);

        return $entry;
    }

    /** @return array<mixed> */
    private static function props(Crawler $crawler): array
    {
        return Json::decode($crawler->filter('#site-content')->text());
    }

    /** @return array<mixed> */
    private static function data(Crawler $crawler): array
    {
        return Json::array(self::props($crawler), 'data');
    }
}
