<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\CategoryView;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Presentation\Web\Shop\SiteLabels;
use App\Tests\Functional\Catalogue\PushesCatalogue;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class ShopCataloguePagesTest extends WebTestCase
{
    use PushesCatalogue;
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testACategoryPageListsItsProductsInFrench(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => 'Fox print', 'titleFr' => 'Affiche renard']);
        self::label($client, 'Prints', 'Art prints', 'Affiches');
        self::photo($client, $ids['prints-fox']);

        $crawler = $client->request('GET', '/beta/fr/category/prints');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        $props = Json::decode($crawler->filter('#site-content')->text());
        $data = Json::array($props, 'data');
        self::assertSame(['category', 'Affiches', ['key' => 'prints', 'label' => 'Affiches']], [$data['page'], $data['title'], $data['category']]);

        $fox = Json::array($data, 'products', 0);
        $image = Json::array($fox, 'image');
        self::assertMatchesRegularExpression('{^/media/artworks/\w+\.webp$}', Json::string($image, 'src'));
        self::assertMatchesRegularExpression('{^/media/artworks/\w+\.webp 600w, /media/artworks/\w+\.webp 1600w$}', Json::string($image, 'srcset'));
        self::assertStringStartsWith(Json::string($image, 'src').' 600w', Json::string($image, 'srcset'));
        self::assertSame([1600, 1200, ''], [$image['width'], $image['height'], $image['alt']]);
        self::assertSame([
            ['title' => 'Affiche renard', 'url' => '/beta/fr/shop/prints-fox', 'price' => 1500, 'image' => $image, 'soldOut' => false, 'hidden' => false],
            ['title' => 'Prints Owl', 'url' => '/beta/fr/shop/prints-owl', 'price' => 1200, 'image' => null, 'soldOut' => true, 'hidden' => false],
        ], $data['products']);

        self::assertSame('Affiches · mossyleaf.studio', $crawler->filter('title')->text());
        self::assertSame([['locale' => 'en', 'url' => '/beta/en/category/prints', 'current' => false], ['locale' => 'fr', 'url' => '/beta/fr/category/prints', 'current' => true]], Json::array($props, 'layout', 'languages'));

        $shop = $crawler->filter('#shop main');
        self::assertSame('Affiches', $shop->filter('h1')->text());
        self::assertSame(['/beta/fr/shop/prints-fox', '/beta/fr/shop/prints-owl'], $shop->filter('[data-products] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertStringContainsString("15,00\u{a0}€", $shop->filter('[data-products] li')->first()->text());
        self::assertStringContainsString('Épuisé', $shop->filter('[data-products] li')->eq(1)->text());
        self::assertSame(['eager'], $shop->filter('[data-products] img')->each(static fn (Crawler $image): string => (string) $image->attr('loading')));
        self::assertEveryInternalLinkIsAPreviewLink($crawler);
    }

    public function testACategoryHoldingOnlyHiddenProductsIsShownInThePreviewWithItsProductsFlagged(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);

        $crawler = $client->request('GET', '/beta/en/category/stickers');

        self::assertResponseIsSuccessful();
        $data = self::data($crawler);
        self::assertSame(['Stickers', ['key' => 'stickers', 'label' => 'Stickers']], [$data['title'], $data['category']]);
        self::assertSame([['/beta/en/shop/stickers-moth', true]], array_map(static fn (mixed $card): array => [Json::string($card, 'url'), Json::at($card, 'hidden')], Json::array($data, 'products')));
        self::assertStringContainsString('Hidden', $crawler->filter('#shop main [data-products] li')->text());
    }

    public function testTheShopEntranceLeadsToTheFirstCategoryOfTheMenu(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);

        $client->request('GET', '/beta/en/shop');
        self::assertResponseRedirects('/beta/en/category/prints', 302);

        $ids = array_column(array_map(static fn (CategoryView $category): array => [$category->name, $category->id], self::getContainer()->get(CatalogueQueries::class)->categories()), 1, 0);
        $client->jsonRequest('PUT', '/api/admin/categories/order', ['ids' => [$ids['Stickers'], $ids['Cards'], $ids['Prints']]]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/beta/fr/shop');
        self::assertResponseRedirects('/beta/fr/category/stickers', 302);
        self::assertSame(['stickers', 'prints'], array_map(static fn (mixed $child): string => Json::string($child, 'key'), Json::array(self::categoriesEntry($client->request('GET', '/beta/fr/')), 'children')));
    }

    public function testCategoryKeysAreTheSameInBothLanguagesUniqueAndNeverEmpty(): void
    {
        $client = self::signedInClient();
        foreach (self::push([self::product(1, 'Pens & Inks', 'Nib', 900), self::product(2, 'Prints', 'Fox', 900), self::product(3, 'prints!', 'Owl', 900), self::product(4, '—', 'Moth', 900)]) as $id) {
            self::show($client, $id);
        }

        $keys = [];
        foreach (['en', 'fr'] as $locale) {
            $categories = Json::array(self::categoriesEntry($client->request('GET', "/beta/{$locale}/")), 'children');
            $keys[$locale] = array_map(static fn (mixed $category): string => Json::string($category, 'key'), $categories);
            foreach ($categories as $category) {
                self::assertCount(1, Json::array(self::data($client->request('GET', Json::string($category, 'url'))), 'products'), Json::string($category, 'url'));
            }
        }

        self::assertSame($keys['en'], $keys['fr']);
        self::assertCount(4, array_unique(array_filter($keys['en'])));
        self::assertSame(['pens-inks', 'prints'], \array_slice($keys['en'], 0, 2));
    }

    public function testAnUnknownOrEmptyCategoryAnswersTheShopNotFoundPage(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);

        foreach (['/beta/en/category/nope', '/beta/en/category/cards', '/beta/fr/category/Prints', '/beta/fr/category/prints%00x', '/beta/fr/shop/cards-bee', '/beta/fr/shop/prints-hare', '/beta/fr/shop/no-such-product', '/beta/fr/shop/prints-fox%00x', '/beta/fr/shop/Prints-Fox'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseStatusCodeSame(404, $url);
            self::assertSame('not-found', Json::string(self::data($crawler), 'page'), $url);
        }
    }

    public function testTheProductPageCarriesWhatTheBuyBoxNeeds(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => 'Fox print', 'titleFr' => 'Affiche renard', 'descriptionFr' => "Un renard.\n\nImprimé sur [papier](https://example.com/papier)."]);
        self::label($client, 'Prints', 'Art prints', 'Affiches');
        $client->jsonRequest('PUT', '/api/admin/texts/shop-notes', ['announcement' => '', 'announcementFr' => '', 'priceNote' => 'VAT not applicable', 'priceNoteFr' => 'TVA non applicable']);
        self::photo($client, $ids['prints-fox']);
        self::photo($client, $ids['prints-fox']);

        $crawler = $client->request('GET', '/beta/fr/shop/prints-fox');

        self::assertResponseIsSuccessful();
        $data = self::data($crawler);
        self::assertSame(['product', 'Affiche renard', "Un renard.\n\nImprimé sur [papier](https://example.com/papier).", 1500, 'TVA non applicable'], [$data['page'], $data['title'], $data['description'], $data['price'], $data['priceNote']]);
        self::assertSame(['key' => 'prints', 'label' => 'Affiches', 'url' => '/beta/fr/category/prints'], $data['category']);
        self::assertCount(2, Json::array($data, 'photos'));
        self::assertSame('Affiche renard', Json::string($data, 'photos', 1, 'alt'));
        self::assertSame([['sku' => 'PRI-FOX-A4', 'name' => 'A4', 'available' => 10, 'soldOut' => false, 'state' => 'inStock'], ['sku' => 'PRI-FOX-A5', 'name' => 'A5', 'available' => 0, 'soldOut' => true, 'state' => 'soldOut']], $data['variants']);
        self::assertSame([false, false], [$data['soldOut'], $data['hidden']]);
        self::assertSame([['title' => 'Prints Owl', 'url' => '/beta/fr/shop/prints-owl', 'price' => 1200, 'image' => null, 'soldOut' => true, 'hidden' => false]], $data['related']);

        $main = $crawler->filter('#shop main');
        self::assertSame('Affiche renard', $main->filter('h1')->text());
        self::assertSame([['Affiches', '/beta/fr/category/prints']], $main->filter('[data-category] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        self::assertSame("15,00\u{a0}€", $main->filter('[data-price]')->text());
        self::assertSame('TVA non applicable', $main->filter('[data-price-note]')->text());
        self::assertSame(['Un renard.', 'Imprimé sur papier.'], $main->filter('[data-description] p')->each(static fn (Crawler $paragraph): string => $paragraph->text()));
        self::assertSame('https://example.com/papier', $main->filter('[data-description] a')->attr('href'));
        self::assertCount(2, $main->filter('[data-photos] img'));
        self::assertSame(['A4', 'A5'], $main->filter('[data-variants] [data-variant]')->each(static fn (Crawler $variant): string => $variant->text()));
        self::assertSame(['/beta/fr/shop/prints-owl'], $main->filter('[data-related] [data-products] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertEveryInternalLinkIsAPreviewLink($crawler);
    }

    public function testTheProductPageWritesItsSearchEngineTagsSafely(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        $title = 'Fox "print" </script><script>alert(1)</script>';
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => $title, 'description' => 'A fox among [ferns](https://example.com/ferns), drawn by hand.']);
        self::photo($client, $ids['prints-fox']);

        $crawler = $client->request('GET', '/beta/en/shop/prints-fox');
        $html = (string) $client->getResponse()->getContent();

        self::assertSame($title.' · mossyleaf.studio', $crawler->filter('title')->text());
        self::assertSame($title.' · mossyleaf.studio', $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertSame('A fox among ferns, drawn by hand.', $crawler->filter('meta[name="description"]')->attr('content'));
        $photo = Json::string(self::data($crawler), 'photos', 0, 'srcset');
        $full = 'http://localhost'.explode(' ', trim(explode(',', $photo)[1]))[0];
        self::assertSame($full, $crawler->filter('meta[property="og:image"]')->attr('content'));
        self::assertSame('1600', $crawler->filter('meta[property="og:image:width"]')->attr('content'));
        self::assertSame($title, $crawler->filter('meta[property="og:image:alt"]')->attr('content'));
        self::assertSame(1, substr_count($html, '<script type="application/ld+json">'));
        self::assertCount(1, $crawler->filter('body > script[type="application/ld+json"]'));
        self::assertStringNotContainsString('alert(1)</script>', $html);

        $product = Json::decode($crawler->filter('script[type="application/ld+json"]')->text());
        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title,
            'description' => 'A fox among ferns, drawn by hand.',
            'image' => [$full],
            'url' => 'http://localhost/en/shop/prints-fox',
            'offers' => [
                ['@type' => 'Offer', 'sku' => 'PRI-FOX-A4', 'price' => '15.00', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock', 'url' => 'http://localhost/en/shop/prints-fox'],
                ['@type' => 'Offer', 'sku' => 'PRI-FOX-A5', 'price' => '15.00', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/OutOfStock', 'url' => 'http://localhost/en/shop/prints-fox'],
            ],
        ], $product);
        self::assertSame('http://localhost/en/shop/prints-fox', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertCount(0, $client->request('GET', '/beta/en/category/prints')->filter('script[type="application/ld+json"]'));
    }

    public function testALongProductDescriptionIsCutForSearchEngines(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['description' => 'A fox among [ferns](https://example.com/ferns).'."\n\n".str_repeat('Drawn by hand with ink. ', 12)]);

        $description = (string) $client->request('GET', '/beta/en/shop/prints-fox')->filter('meta[name="description"]')->attr('content');

        self::assertSame(160, mb_strlen($description));
        self::assertStringStartsWith('A fox among ferns. Drawn by hand', $description);
        self::assertStringEndsWith('…', $description);
    }

    public function testTheHomeCarriesTheLatestProductsAndTheCategoryTiles(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::photo($client, $ids['prints-fox']);

        $crawler = $client->request('GET', '/beta/en/');
        $data = self::data($crawler);

        self::assertSame('/beta/en/category/prints', $data['shopUrl']);
        self::assertArrayNotHasKey('products', $data);
        self::assertArrayNotHasKey('picks', $data);
        self::assertSame(['/beta/en/shop/stickers-moth', '/beta/en/shop/prints-owl', '/beta/en/shop/prints-fox'], array_map(static fn (mixed $card): string => Json::string($card, 'url'), Json::array($data, 'latest')));
        self::assertSame([['prints', 'Prints', '/beta/en/category/prints'], ['stickers', 'Stickers', '/beta/en/category/stickers']], array_map(static fn (mixed $tile): array => [Json::string($tile, 'key'), Json::string($tile, 'label'), Json::string($tile, 'url')], Json::array($data, 'categories')));
        self::assertSame(Json::array($data, 'latest', 2, 'image'), Json::array($data, 'categories', 0, 'image'));
        self::assertNull(Json::at($data, 'categories', 1, 'image'));

        $main = $crawler->filter('#shop main');
        self::assertSame([['Visit the shop', '/beta/en/category/prints']], $main->filter('a[data-shop]')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        self::assertSame(['/beta/en/shop/stickers-moth', '/beta/en/shop/prints-owl', '/beta/en/shop/prints-fox'], $main->filter('[data-latest] [data-products] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSame(['lazy'], $main->filter('[data-latest] [data-products] img')->each(static fn (Crawler $image): string => (string) $image->attr('loading')));
        self::assertCount(0, $main->filter('[data-latest] > p'));
        self::assertCount(0, $main->filter('[data-picks]'));
        self::assertCount(0, $main->filter('[data-products] [style], [data-tiles] [style]'));
        self::assertSame([['Prints', '/beta/en/category/prints'], ['Stickers', '/beta/en/category/stickers']], $main->filter('[data-tiles] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]));
        self::assertEveryInternalLinkIsAPreviewLink($crawler);
    }

    public function testTheHomeCarriesTheStudioPicksInStudioOrderAndTheNewestProductsFirst(): void
    {
        $client = self::signedInClient();
        $ids = self::push(array_map(static fn (int $number): PushedProduct => self::product($number, 'Prints', 'Fern'.$number, 900), range(1, 14)));
        foreach ($ids as $slug => $id) {
            self::show($client, $id);
            if ('prints-fern1' !== $slug) {
                self::pick($client, $id);
            }
        }
        $client->jsonRequest('PUT', '/api/admin/products/order', ['ids' => [$ids['prints-fern14'], ...array_values(array_diff($ids, [$ids['prints-fern14']]))]]);
        self::assertResponseIsSuccessful();

        $data = self::data($client->request('GET', '/beta/en/'));

        $url = static fn (int $number): string => "/beta/en/shop/prints-fern{$number}";
        self::assertSame(array_map($url, [14, ...range(2, 12)]), self::cardUrls(Json::array($data, 'picks')));
        self::assertSame(array_map($url, range(14, 3)), self::cardUrls(Json::array($data, 'latest')));
    }

    public function testTheHomePicksLeaveOutWhatCannotBeSoldAndFlagWhatIsHidden(): void
    {
        $client = self::signedInClient();
        $ids = self::push(self::demo());
        foreach (['prints-fox', 'prints-owl', 'prints-hare', 'cards-bee'] as $slug) {
            self::show($client, $ids[$slug]);
        }
        foreach (['stickers-moth', 'prints-hare', 'cards-bee', 'prints-fox'] as $slug) {
            self::pick($client, $ids[$slug]);
        }
        self::push(\array_slice(self::demo(), 0, 4));

        $crawler = $client->request('GET', '/beta/fr/');
        $data = self::data($crawler);

        self::assertSame([['/beta/fr/shop/prints-fox', false], ['/beta/fr/shop/stickers-moth', true]], array_map(static fn (mixed $card): array => [Json::string($card, 'url'), Json::at($card, 'hidden')], Json::array($data, 'picks')));
        self::assertSame(['/beta/fr/shop/stickers-moth', '/beta/fr/shop/prints-owl', '/beta/fr/shop/prints-fox'], self::cardUrls(Json::array($data, 'latest')));

        $main = $crawler->filter('#shop main');
        foreach (['picks' => 'home.picks', 'latest' => 'home.latest'] as $key => $label) {
            $section = $main->filter("[data-{$key}]");
            self::assertCount(1, $section, $key);
            self::assertSame(self::siteLabel('fr', $label), $section->filter('h2')->text(), $key);
            self::assertSame($section->filter('h2')->attr('id'), $section->attr('aria-labelledby'), $key);
            self::assertSame(self::cardUrls(Json::array($data, $key)), $section->filter('[data-products] > li a')->each(static fn (Crawler $link): string => (string) $link->attr('href')), $key);
            self::assertSame(
                array_map(static fn (mixed $card): array => [Json::string($card, 'title'), self::price('fr', Json::int($card, 'price')), [...(true === Json::at($card, 'soldOut') ? [self::siteLabel('fr', 'product.soldOut')] : []), ...(true === Json::at($card, 'hidden') ? [self::siteLabel('fr', 'product.hidden')] : [])]], Json::array($data, $key)),
                $section->filter('[data-products] > li')->each(static fn (Crawler $card): array => [$card->filter('[data-card-title]')->text(), $card->filter('[data-card-price]')->text(), $card->filter('[data-label]')->each(static fn (Crawler $tape): string => $tape->text())]),
                $key,
            );
        }
        self::assertEveryInternalLinkIsAPreviewLink($crawler);
    }

    public function testThePrerenderLoadsOnlyTheFirstImageOfTheFirstHomeRowEagerly(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::photo($client, $ids['prints-fox']);
        self::photo($client, $ids['stickers-moth']);
        $loading = static fn (string $key): array => $client->getCrawler()->filter("#shop main [data-{$key}] [data-products] img")->each(static fn (Crawler $image): string => (string) $image->attr('loading'));

        $client->request('GET', '/beta/en/');
        self::assertSame(['eager', 'lazy'], $loading('latest'));

        self::pick($client, $ids['prints-fox']);
        $client->request('GET', '/beta/en/');
        self::assertSame(['eager'], $loading('picks'));
        self::assertSame(['lazy', 'lazy'], $loading('latest'));
    }

    public function testTheHomeLeavesTheShopSectionsAndTheCategoriesEntryOutWhenTheCatalogueShowsNothing(): void
    {
        $client = self::signedInClient();
        $ids = self::push([self::product(1, 'Prints', 'Hare', 0, 5)]);
        self::pick($client, $ids['prints-hare']);

        $client->request('GET', '/beta/fr/shop');
        self::assertResponseRedirects('/beta/fr/', 302);

        $crawler = $client->request('GET', '/beta/fr/');
        $data = self::data($crawler);
        $navigation = Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'navigation');
        self::assertSame(['home', 'conventions', 'about', 'contact'], array_map(static fn (mixed $entry): string => Json::string($entry, 'key'), $navigation));
        self::assertCount(0, $crawler->filter('#shop [data-categories], #shop [data-menu], #shop #menu-categories, #shop #menu-collections'));
        self::assertNull($data['shopUrl']);
        self::assertCount(0, $crawler->filter('#shop main a[data-shop]'));

        self::assertArrayNotHasKey('picks', $data);
        self::assertArrayNotHasKey('latest', $data);
        self::assertArrayNotHasKey('products', $data);
        self::assertArrayNotHasKey('categories', $data);
        self::assertCount(0, $crawler->filter('#shop main [data-picks], #shop main [data-latest], #shop main [data-tiles]'));
    }

    public function testTheHomeDoesNotRunOneQueryPerProduct(): void
    {
        $client = self::signedInClient();
        self::stockFerns($client, 2, true);
        $few = self::homeQueries($client);

        self::stockFerns($client, 8, true);

        self::assertSame($few, self::homeQueries($client));
    }

    /**
     * @param array<mixed> $cards
     *
     * @return list<string>
     */
    private static function cardUrls(array $cards): array
    {
        return array_values(array_map(static fn (mixed $card): string => Json::string($card, 'url'), $cards));
    }

    private static function homeQueries(KernelBrowser $client): int
    {
        $client->enableProfiler();
        $client->request('GET', '/beta/en/');
        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    public function testThePrerenderOfACategoryShowsWhatThePropsCarry(): void
    {
        $client = self::signedInClient();
        self::stockTheDemo($client);

        foreach (['fr/category/prints' => 'fr', 'en/category/prints' => 'en', 'fr/category/stickers' => 'fr'] as $path => $locale) {
            $crawler = $client->request('GET', "/beta/{$path}");
            self::assertSame(Json::string(self::data($crawler), 'title'), $crawler->filter('#shop main h1')->text(), $path);
            $cards = Json::array(self::data($crawler), 'products');

            self::assertSame(
                array_map(static fn (mixed $card): array => [
                    Json::string($card, 'url'),
                    Json::string($card, 'title'),
                    self::price($locale, Json::int($card, 'price')),
                    [...(true === Json::at($card, 'soldOut') ? [self::siteLabel($locale, 'product.soldOut')] : []), ...(true === Json::at($card, 'hidden') ? [self::siteLabel($locale, 'product.hidden')] : [])],
                ], $cards),
                $crawler->filter('#shop main [data-products] > li')->each(static fn (Crawler $card): array => [
                    (string) $card->filter('a')->attr('href'),
                    $card->filter('[data-card-title]')->text(),
                    $card->filter('[data-card-price]')->text(),
                    $card->filter('[data-label]')->each(static fn (Crawler $label): string => $label->text()),
                ]),
                $locale,
            );
        }
        self::assertSame(['Masqué', 'Épuisé'], [self::siteLabel('fr', 'product.hidden'), self::siteLabel('fr', 'product.soldOut')]);
    }

    public function testThePrerenderOfAProductShowsWhatThePropsCarry(): void
    {
        $client = self::signedInClient();
        $ids = self::push([...self::demo(), self::product(6, 'Prints', 'Wren', 2000, ['A3' => 0, 'A4' => 2])]);
        foreach (['prints-fox', 'prints-owl', 'prints-wren'] as $slug) {
            self::show($client, $ids[$slug]);
        }

        foreach (['prints-fox' => 'En stock', 'prints-owl' => 'Épuisé', 'prints-wren' => 'Dernières pièces', 'stickers-moth' => 'En stock'] as $slug => $stock) {
            $crawler = $client->request('GET', "/beta/fr/shop/{$slug}");
            $data = self::data($crawler);
            $main = $crawler->filter('#shop main');

            self::assertSame(Json::string($data, 'title'), $main->filter('h1')->text(), $slug);
            self::assertSame(
                [['Accueil', '/beta/fr/'], [Json::string($data, 'category', 'label'), Json::string($data, 'category', 'url')]],
                $main->filter('nav[data-breadcrumb] a')->each(static fn (Crawler $link): array => [$link->text(), (string) $link->attr('href')]),
                $slug,
            );
            self::assertSame('page', $main->filter('nav[data-breadcrumb] [aria-current]')->attr('aria-current'));
            self::assertSame(Json::string($data, 'title'), $main->filter('nav[data-breadcrumb] [aria-current]')->text());
            self::assertSame(self::price('fr', Json::int($data, 'price')), $main->filter('[data-price]')->text(), $slug);
            self::assertSame($stock, $main->filter('[data-stock]')->text(), $slug);
            self::assertCount(true === $data['hidden'] ? 1 : 0, $main->filter('[data-hidden]'), $slug);
        }

        $wren = $client->request('GET', '/beta/fr/shop/prints-wren');
        self::assertSame(['A3 Épuisé', 'A4'], $wren->filter('#shop main [data-variants] li')->each(static fn (Crawler $variant): string => $variant->text()));
        self::assertSame(['A4'], $wren->filter('#shop main [data-variants] li[aria-current="true"]')->each(static fn (Crawler $variant): string => $variant->text()));
        self::assertSame('Masqué', $client->request('GET', '/beta/fr/shop/stickers-moth')->filter('#shop main [data-hidden] [data-label]')->text());
        self::assertCount(0, $client->getCrawler()->filter('#shop main [data-variants]'));
    }

    private static function price(string $locale, int $cents): string
    {
        return (string) \NumberFormatter::create($locale, \NumberFormatter::CURRENCY)->formatCurrency($cents / 100, 'EUR');
    }

    private static function siteLabel(string $locale, string $key): string
    {
        return self::getContainer()->get(SiteLabels::class)->label($locale, $key);
    }

    private static function stockFerns(KernelBrowser $client, int $count, bool $picked = false): void
    {
        foreach (self::push(array_map(static fn (int $number): PushedProduct => self::product($number, 'Prints', 'Fern'.$number, 900, ['A4' => 2, 'A5' => 3]), range(1, $count))) as $id) {
            self::show($client, $id);
            if ($picked) {
                self::pick($client, $id);
            }
            self::photo($client, $id);
        }
    }

    /** @return array<mixed> */
    private static function categoriesEntry(Crawler $crawler): array
    {
        $entry = array_find(Json::array(Json::decode($crawler->filter('#site-content')->text()), 'layout', 'navigation'), static fn (mixed $entry): bool => 'categories' === Json::at($entry, 'key'));
        self::assertIsArray($entry);

        return $entry;
    }

    /** @return array<mixed> */
    private static function data(Crawler $crawler): array
    {
        return Json::array(Json::decode($crawler->filter('#site-content')->text()), 'data');
    }

    private static function assertEveryInternalLinkIsAPreviewLink(Crawler $crawler): void
    {
        $internal = array_filter(
            [
                ...$crawler->filter('#shop .shop-prerender > :not([data-draft]) a')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
                ...self::urls(Json::decode($crawler->filter('#site-content')->text())),
            ],
            static fn (string $href): bool => str_starts_with($href, '/') && !str_starts_with($href, '/media/'),
        );

        self::assertNotEmpty($internal);
        foreach ($internal as $href) {
            self::assertStringStartsWith('/beta/', $href);
        }
    }

    /** @return list<string> */
    private static function urls(mixed $props): array
    {
        if (!\is_array($props)) {
            return [];
        }

        $urls = [];
        foreach ($props as $key => $value) {
            $urls = [...$urls, ...('url' === $key && \is_string($value) ? [$value] : self::urls($value))];
        }

        return $urls;
    }
}
