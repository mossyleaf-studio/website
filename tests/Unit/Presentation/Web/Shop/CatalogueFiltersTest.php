<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Shop;

use App\Application\Catalogue\ShopCategoryView;
use App\Application\Catalogue\ShopCollectionView;
use App\Application\Catalogue\ShopProductView;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\Collection;
use App\Domain\Catalogue\Product;
use App\Domain\Shared\Translation;
use App\Presentation\Web\Shop\CatalogueFilters;
use App\Presentation\Web\Shop\CatalogueProps;
use App\Presentation\Web\Shop\ShopUrls;
use App\Presentation\Web\Shop\SiteLabels;
use App\Tests\Support\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Uid\Ulid;

final class CatalogueFiltersTest extends TestCase
{
    public function testEveryProductIsShownWithAnAllChipFirstWhenNothingIsChosen(): void
    {
        $products = [self::product('fern', 'forest', 0), self::product('moss', 'meadow', 1), self::product('bark', null, 2)];

        [$shown, $props] = self::filters()->apply(self::request('shop_category', ['category' => 'prints']), CatalogueFilters::COLLECTION, $products, false);

        self::assertSame($products, $shown);
        self::assertSame(
            ['collection', [
                ['key' => null, 'label' => 'All', 'url' => '/shop_category/en?category=prints', 'current' => true],
                ['key' => 'forest', 'label' => 'Forest', 'url' => '/shop_category/en?category=prints&collection=forest', 'current' => false],
                ['key' => 'meadow', 'label' => 'Meadow', 'url' => '/shop_category/en?category=prints&collection=meadow', 'current' => false],
            ]],
            [Json::string($props, 'filters', 'kind'), self::items($props)],
        );
    }

    public function testTheChosenOptionKeepsItsProductsAndIsTheCurrentChip(): void
    {
        $fern = self::product('fern', 'forest', 0);
        $products = [$fern, self::product('moss', 'meadow', 1), self::product('bark', null, 2)];

        [$shown, $props] = self::filters()->apply(self::request('shop_category', ['category' => 'prints'], ['collection' => 'forest']), CatalogueFilters::COLLECTION, $products, false);

        self::assertSame([$fern], $shown);
        self::assertSame([false, true, false], array_column(self::items($props), 'current'));
    }

    public function testTheCategoryKindFiltersOnTheCategoryOfTheProduct(): void
    {
        $fern = self::product('fern', null, 0, 'prints');
        $products = [$fern, self::product('sticker', null, 1, 'stickers')];

        [$shown, $props] = self::filters()->apply(self::request('shop_collection', ['collection' => 'forest'], ['category' => 'prints']), CatalogueFilters::CATEGORY, $products, false);

        self::assertSame([$fern], $shown);
        self::assertSame(['category', [null, 'prints', 'stickers']], [Json::string($props, 'filters', 'kind'), array_column(self::items($props), 'key')]);
    }

    public function testAnOptionNoProductCarriesIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        self::filters()->apply(self::request('shop_category', [], ['collection' => 'desert']), CatalogueFilters::COLLECTION, [self::product('fern', 'forest', 0), self::product('moss', 'meadow', 1)], false);
    }

    public function testAChoiceOnAPageWithoutOptionsIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        self::filters()->apply(self::request('shop_category', [], ['collection' => 'forest']), CatalogueFilters::COLLECTION, [self::product('fern', null, 0)], false);
    }

    public function testNothingFiltersWithoutOptionOrWithASingleOptionEveryProductCarries(): void
    {
        $filters = self::filters();
        $request = self::request('shop_category', ['category' => 'prints']);
        $without = [self::product('fern', null, 0)];
        $single = [self::product('fern', 'forest', 0), self::product('moss', 'forest', 1)];

        self::assertSame([$without, []], $filters->apply($request, CatalogueFilters::COLLECTION, $without, false));
        self::assertSame([$single, []], $filters->apply($request, CatalogueFilters::COLLECTION, $single, false));
    }

    public function testASingleOptionStillFiltersWhenAProductDoesNotCarryIt(): void
    {
        $products = [self::product('fern', 'forest', 0), self::product('moss', null, 1)];

        [$shown, $props] = self::filters()->apply(self::request('shop_category', []), CatalogueFilters::COLLECTION, $products, false);

        self::assertSame($products, $shown);
        self::assertSame([null, 'forest'], array_column(self::items($props), 'key'));
    }

    public function testOptionsFollowTheirPositionAndThePreviewLinksStayInThePreview(): void
    {
        $products = [self::product('moss', 'meadow', 1), self::product('fern', 'forest', 0)];

        [, $props] = self::filters()->apply(self::request('beta_shop_category', ['category' => 'prints']), CatalogueFilters::COLLECTION, $products, true);

        self::assertSame([null, 'forest', 'meadow'], array_column(self::items($props), 'key'));
        self::assertSame(['/beta_shop_category/en?category=prints', '/beta_shop_category/en?category=prints&collection=forest', '/beta_shop_category/en?category=prints&collection=meadow'], array_column(self::items($props), 'url'));
    }

    public function testTheLinksFollowThePageLanguage(): void
    {
        $products = [self::product('fern', 'forest', 0), self::product('moss', 'meadow', 1)];

        [, $props] = self::filters()->apply(self::request('shop_category', [], [], 'fr'), CatalogueFilters::COLLECTION, $products, false);

        self::assertSame(['Tout', '/shop_category/fr'], [Json::string($props, 'filters', 'items', 0, 'label'), Json::string($props, 'filters', 'items', 0, 'url')]);
    }

    /**
     * @param array<mixed> $props
     *
     * @return list<array<mixed>>
     */
    private static function items(array $props): array
    {
        return array_values(array_map(Json::array(...), Json::array($props, 'filters', 'items')));
    }

    private static function filters(): CatalogueFilters
    {
        $urls = new ShopUrls(new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                $locale = Json::string($parameters, '_locale');
                unset($parameters['_locale']);

                return '/'.$name.'/'.$locale.([] === $parameters ? '' : '?'.http_build_query($parameters));
            }
        });

        return new CatalogueFilters($urls, new CatalogueProps($urls), new SiteLabels(\dirname(__DIR__, 5).'/assets/vue/i18n'));
    }

    /**
     * @param array<string, string> $routeParameters
     * @param array<string, string> $query
     */
    private static function request(string $route, array $routeParameters, array $query = [], string $locale = 'en'): Request
    {
        return new Request($query, [], ['_route' => $route, '_locale' => $locale, '_route_params' => ['_locale' => $locale, ...$routeParameters]]);
    }

    private static function product(string $slug, ?string $collection, int $position, string $category = 'prints'): ShopProductView
    {
        $product = Product::pushed(new Ulid(), strtoupper($slug), $slug, $slug, Category::named($category, 0), 1000, $slug, $position);
        $view = null;
        if (null !== $collection) {
            $entity = Collection::pushed(new Ulid(), ucfirst($collection), 'forest' === $collection ? 0 : 1);
            $entity->relabel(Translation::of(ucfirst($collection), ucfirst($collection)));
            $view = ShopCollectionView::of($entity, $collection);
        }

        return ShopProductView::of($product, ShopCategoryView::of($product->category(), $category), $view, []);
    }
}
