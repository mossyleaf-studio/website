<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\ShopCategoryView;
use App\Application\Catalogue\ShopCollectionView;
use App\Application\Catalogue\ShopProductView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CatalogueFilters
{
    public const string COLLECTION = 'collection';
    public const string CATEGORY = 'category';

    public function __construct(
        private ShopUrls $urls,
        private CatalogueProps $props,
        private SiteLabels $labels,
    ) {
    }

    /**
     * @param self::COLLECTION|self::CATEGORY $kind
     * @param list<ShopProductView>           $products
     *
     * @return array{list<ShopProductView>, array{filters?: array{kind: string, items: list<array<string, mixed>>}}}
     */
    public function apply(Request $request, string $kind, array $products, bool $preview): array
    {
        $chosen = $request->query->all()[$kind] ?? null;
        $options = self::COLLECTION === $kind ? ShopCollectionView::shownIn($products) : ShopCategoryView::shownIn($products);
        if (null !== $chosen && null === array_find($options, static fn (ShopCollectionView|ShopCategoryView $option): bool => $option->key === $chosen)) {
            throw new NotFoundHttpException();
        }
        if (self::filtersNothing($kind, $options, $products)) {
            return [$products, []];
        }

        $items = $this->items($request, $kind, $options, $chosen, $preview);
        $shown = null === $chosen ? $products : array_values(array_filter($products, static fn (ShopProductView $product): bool => self::key($kind, $product) === $chosen));

        return [$shown, ['filters' => ['kind' => $kind, 'items' => $items]]];
    }

    private static function key(string $kind, ShopProductView $product): ?string
    {
        return self::COLLECTION === $kind ? $product->collection?->key : $product->category->key;
    }

    /**
     * @param list<ShopCollectionView|ShopCategoryView> $options
     * @param list<ShopProductView>                     $products
     */
    private static function filtersNothing(string $kind, array $options, array $products): bool
    {
        return [] === $options || (1 === \count($options) && array_all($products, static fn (ShopProductView $product): bool => self::key($kind, $product) === $options[0]->key));
    }

    /**
     * @param list<ShopCollectionView|ShopCategoryView> $options
     *
     * @return list<array<string, mixed>>
     */
    private function items(Request $request, string $kind, array $options, mixed $chosen, bool $preview): array
    {
        $locale = ShopUrls::locale($request);
        $route = ShopUrls::route($request);
        $parameters = ShopUrls::parameters($request);
        $items = [['key' => null, 'label' => $this->labels->label($locale->value, 'filter.all'), 'url' => $this->urls->to($route, $locale, $preview, $parameters), 'current' => null === $chosen]];
        foreach ($options as $option) {
            $link = $option instanceof ShopCollectionView ? $this->props->collection($option, $locale, $preview) : $this->props->category($option, $locale, $preview);
            $items[] = [...$link, 'url' => $this->urls->to($route, $locale, $preview, [...$parameters, $kind => $option->key]), 'current' => $option->key === $chosen];
        }

        return $items;
    }
}
