<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Catalogue\Collection;
use App\Domain\Catalogue\CollectionRepository;
use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Sales\OrderRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class CatalogueQueries
{
    private const int RELATED = 5;

    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private SluggerInterface $slugger,
        private OrderRepository $orders,
        private ClockInterface $clock,
        private CollectionRepository $collections,
    ) {
    }

    /** @return list<ProductView> */
    public function products(): array
    {
        return ProductView::list($this->products->all());
    }

    /** @return list<CategoryView> */
    public function categories(): array
    {
        return CategoryView::list($this->categories->all());
    }

    /** @return list<CollectionView> */
    public function collections(): array
    {
        return CollectionView::list($this->collections->all(), $this->collections->productsOnSale());
    }

    /** @return list<ShopProductView> */
    public function shop(bool $preview): array
    {
        $categoryKeys = $this->categoryKeys();
        $collectionKeys = $this->collectionKeys();
        $held = $this->held();

        return array_map(fn (Product $product): ShopProductView => $this->shopView($product, $preview, $categoryKeys, $collectionKeys, $held), $this->products->forSale($preview));
    }

    /** @return list<ShopCategoryView> */
    public function shopCategories(bool $preview): array
    {
        $keys = $this->categoryKeys();

        return ShopCategoryView::ordered(array_map(static fn (Category $category): ShopCategoryView => ShopCategoryView::of($category, $keys[(string) $category->id()]), $this->categories->forSale($preview)));
    }

    /** @return list<ShopCollectionView> */
    public function shopCollections(bool $preview): array
    {
        $keys = $this->collectionKeys();

        return ShopCollectionView::ordered(array_map(static fn (Collection $collection): ShopCollectionView => ShopCollectionView::of($collection, $keys[(string) $collection->id()]), $this->collections->forSale($preview)));
    }

    public function shopProduct(string $slug, bool $preview): ?ShopProductView
    {
        $product = $this->products->withSlug($slug);

        return null !== $product && self::shows($product, $preview) ? $this->shopView($product, $preview, $this->categoryKeys(), $this->collectionKeys(), $this->held()) : null;
    }

    /** @return list<ShopProductView> */
    public function related(ShopProductView $product, bool $preview): array
    {
        return \array_slice(array_values(array_filter(
            $this->shop($preview),
            static fn (ShopProductView $other): bool => $other->category->key === $product->category->key && $other->id !== $product->id,
        )), 0, self::RELATED);
    }

    private static function shows(Product $product, bool $preview): bool
    {
        return !$product->isRetired() && $product->price() > 0 && ($preview || $product->isVisible());
    }

    /**
     * @param array<string, string> $categoryKeys
     * @param array<string, string> $collectionKeys
     * @param array<string, int>    $held
     */
    private function shopView(Product $product, bool $preview, array $categoryKeys, array $collectionKeys, array $held): ShopProductView
    {
        $category = $product->category();
        $collection = $product->collection();
        $seen = null !== $collection && ($preview || $collection->isShown());

        return ShopProductView::of($product, ShopCategoryView::of($category, $categoryKeys[(string) $category->id()]), $seen ? ShopCollectionView::of($collection, $collectionKeys[(string) $collection->id()]) : null, $held);
    }

    /** @return array<string, int> */
    private function held(): array
    {
        return $this->orders->heldUnits($this->clock->now());
    }

    /** @return array<string, string> */
    private function categoryKeys(): array
    {
        return $this->keys(array_map(static fn (Category $category): array => [(string) $category->id(), $category->name()], $this->categories->all()));
    }

    /** @return array<string, string> */
    private function collectionKeys(): array
    {
        return $this->keys(array_map(static fn (Collection $collection): array => [(string) $collection->id(), $collection->name()], $this->collections->all()));
    }

    /**
     * @param list<array{string, string}> $named
     *
     * @return array<string, string>
     */
    private function keys(array $named): array
    {
        usort($named, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));
        $keys = [];
        foreach ($named as [$id, $name]) {
            $slug = $this->slugger->slug($name, '-', ReplaceCatalogueHandler::SLUG_LOCALE)->lower()->toString();
            $keys[$id] = '' === $slug || \in_array($slug, $keys, true) ? strtolower($id) : $slug;
        }

        return $keys;
    }
}
