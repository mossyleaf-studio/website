<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
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

    /** @return list<ShopProductView> */
    public function shop(bool $preview): array
    {
        $keys = $this->categoryKeys();
        $held = $this->held();

        return array_map(fn (Product $product): ShopProductView => $this->shopView($product, $keys, $held), $this->products->forSale($preview));
    }

    /** @return list<ShopCategoryView> */
    public function shopCategories(bool $preview): array
    {
        $keys = $this->categoryKeys();

        return ShopCategoryView::ordered(array_map(static fn (Category $category): ShopCategoryView => ShopCategoryView::of($category, $keys[(string) $category->id()]), $this->categories->forSale($preview)));
    }

    public function shopProduct(string $slug, bool $preview): ?ShopProductView
    {
        $product = $this->products->withSlug($slug);

        return null !== $product && self::shows($product, $preview) ? $this->shopView($product, $this->categoryKeys(), $this->held()) : null;
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
     * @param array<string, string> $keys
     * @param array<string, int>    $held
     */
    private function shopView(Product $product, array $keys, array $held): ShopProductView
    {
        $category = $product->category();

        return ShopProductView::of($product, ShopCategoryView::of($category, $keys[(string) $category->id()]), $held);
    }

    /** @return array<string, int> */
    private function held(): array
    {
        return $this->orders->heldUnits($this->clock->now());
    }

    /** @return array<string, string> */
    private function categoryKeys(): array
    {
        $categories = $this->categories->all();
        usort($categories, static fn (Category $a, Category $b): int => strcmp((string) $a->id(), (string) $b->id()));
        $keys = [];
        foreach ($categories as $category) {
            $id = (string) $category->id();
            $slug = $this->slugger->slug($category->name(), '-', ReplaceCatalogueHandler::SLUG_LOCALE)->lower()->toString();
            $keys[$id] = '' === $slug || \in_array($slug, $keys, true) ? strtolower($id) : $slug;
        }

        return $keys;
    }
}
