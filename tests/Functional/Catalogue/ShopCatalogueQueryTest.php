<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalogue;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ReplaceCatalogue\PushedProduct;
use App\Application\Catalogue\ReplaceCatalogue\PushedVariant;
use App\Application\Catalogue\ShopCategoryView;
use App\Application\Catalogue\ShopProductView;
use App\Application\Catalogue\ShopVariantView;
use App\Domain\Shared\Locale;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final class ShopCatalogueQueryTest extends WebTestCase
{
    use PushesCatalogue;
    use SignsInClient;

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname(__DIR__, 3).'/var/share/test');
        parent::tearDown();
    }

    public function testThePublicSeesListedProductsOnlyAndThePreviewAddsTheHiddenOnesFlagged(): void
    {
        self::stockTheDemo(self::signedInClient());

        self::assertSame([['prints-fox', false], ['prints-owl', false]], self::slugs(self::queries()->shop(false)));
        self::assertSame([['prints-fox', false], ['prints-owl', false], ['stickers-moth', true]], self::slugs(self::queries()->shop(true)));
    }

    public function testTheAdminOrderIsKept(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);

        $client->jsonRequest('PUT', '/api/admin/products/order', ['ids' => array_values(array_reverse($ids))]);
        self::assertResponseIsSuccessful();

        self::assertSame(['stickers-moth', 'prints-owl', 'prints-fox'], array_column(self::slugs(self::queries()->shop(true)), 0));
    }

    public function testTitlesAndCategoryLabelsFallBackToTheOtherLanguageThenToMossytrunk(): void
    {
        $client = self::signedInClient();
        $ids = self::stockTheDemo($client);
        self::describe($client, $ids['prints-fox'], 'prints-fox', ['title' => 'Fox print', 'description' => 'A fox among [ferns](https://example.com/ferns).']);
        self::describe($client, $ids['prints-owl'], 'prints-owl', ['titleFr' => 'Hibou']);
        self::label($client, 'Prints', '', 'Affiches');

        $products = self::queries()->shop(true);

        foreach (Locale::cases() as $locale) {
            self::assertSame(['Fox print', 'Hibou', 'Stickers Moth'], array_map(static fn (ShopProductView $product): string => $product->title->in($locale), $products), $locale->value);
            self::assertSame(['Affiches', 'Affiches', 'Stickers'], array_map(static fn (ShopProductView $product): string => $product->category->label->in($locale), $products), $locale->value);
            self::assertSame(['A fox among [ferns](https://example.com/ferns).', '', ''], array_map(static fn (ShopProductView $product): string => $product->description->in($locale), $products), $locale->value);
        }
        self::assertSame(['prints', 'prints', 'stickers'], array_map(static fn (ShopProductView $product): string => $product->category->key, $products));
    }

    public function testStockIsPublishedCappedAtTenAndAProductIsSoldOutWhenNoVariantCanBeBought(): void
    {
        self::stockTheDemo(self::signedInClient());

        [$fox, $owl, $moth] = self::queries()->shop(true);

        self::assertSame([['PRI-FOX-A4', 'A4', 10, false, 'inStock'], ['PRI-FOX-A5', 'A5', 0, true, 'soldOut']], self::variants($fox));
        self::assertFalse($fox->soldOut);
        self::assertSame([['PRI-OWL', null, 0, true, 'soldOut']], self::variants($owl));
        self::assertTrue($owl->soldOut);
        self::assertSame([['STI-MOTH', null, 4, false, 'inStock']], self::variants($moth));
        self::assertFalse($moth->soldOut);
    }

    public function testThreeOrFewerAreTheLastPiecesAndAnUnnamedVariantAmongSeveralIsNamedByItsSku(): void
    {
        self::push([new PushedProduct(new Ulid('01JD3K7Q000000000000000001'), 'PRI-WREN', 'Wren', 'Prints', 'Prints Wren', 900, [new PushedVariant('A4', 'PRI-WREN-A4', 3), new PushedVariant(null, 'PRI-WREN-X', 1)])]);

        $wren = self::queries()->shopProduct('prints-wren', true);

        self::assertNotNull($wren);
        self::assertSame([['PRI-WREN-A4', 'A4', 3, false, 'lastPieces'], ['PRI-WREN-X', 'PRI-WREN-X', 1, false, 'lastPieces']], self::variants($wren));
    }

    public function testAProductIsFoundBySlugOnlyWhenItIsShown(): void
    {
        self::stockTheDemo(self::signedInClient());
        $queries = self::queries();

        self::assertSame('prints-fox', $queries->shopProduct('prints-fox', false)?->slug);
        self::assertNull($queries->shopProduct('stickers-moth', false));
        self::assertTrue($queries->shopProduct('stickers-moth', true)?->hidden);
        foreach (['cards-bee', 'prints-hare', 'no-such-product'] as $slug) {
            self::assertNull($queries->shopProduct($slug, false), $slug);
            self::assertNull($queries->shopProduct($slug, true), $slug);
        }
    }

    public function testRelatedProductsShareTheCategoryLeaveTheProductOutAndStopAtFour(): void
    {
        $client = self::signedInClient();
        $ids = self::push([...self::demo(), ...array_map(static fn (int $number): PushedProduct => self::product(10 + $number, 'Prints', 'Fern'.$number, 900), range(1, 5))]);
        foreach ($ids as $id) {
            self::show($client, $id);
        }
        $queries = self::queries();

        $fox = $queries->shopProduct('prints-fox', false);
        self::assertNotNull($fox);
        self::assertSame(['prints-owl', 'prints-fern1', 'prints-fern2', 'prints-fern3'], array_column(self::slugs($queries->related($fox, false)), 0));

        $moth = $queries->shopProduct('stickers-moth', true);
        self::assertNotNull($moth);
        self::assertSame([], $queries->related($moth, true));
    }

    public function testOnlyTheCategoriesWithAShownProductAreOffered(): void
    {
        self::stockTheDemo(self::signedInClient());

        self::assertSame(['prints'], array_map(static fn (ShopCategoryView $category): string => $category->key, ShopCategoryView::shownIn(self::queries()->shop(false))));
        self::assertSame(['prints', 'stickers'], array_map(static fn (ShopCategoryView $category): string => $category->key, ShopCategoryView::shownIn(self::queries()->shop(true))));
    }

    private static function queries(): CatalogueQueries
    {
        return self::getContainer()->get(CatalogueQueries::class);
    }

    /**
     * @param list<ShopProductView> $products
     *
     * @return list<array{string, bool}>
     */
    private static function slugs(array $products): array
    {
        return array_map(static fn (ShopProductView $product): array => [$product->slug, $product->hidden], $products);
    }

    /** @return list<array{string, ?string, int, bool, string}> */
    private static function variants(ShopProductView $product): array
    {
        return array_map(static fn (ShopVariantView $variant): array => [$variant->sku, $variant->name, $variant->available, $variant->soldOut, $variant->state], $product->variants);
    }
}
