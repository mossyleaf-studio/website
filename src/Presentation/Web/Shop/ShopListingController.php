<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ShopCategoryView;
use App\Application\Catalogue\ShopProductView;
use App\Application\Content\ContentQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/shop', name: 'shop_listing', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ShopListingController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $content,
        private CatalogueQueries $catalogue,
        private CatalogueProps $props,
        private SiteLabels $labels,
        private ShopPage $page,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $products = $this->catalogue->shop($preview);
        $categories = ShopCategoryView::shownIn($products);
        $key = $request->query->getString('category');
        $current = null;
        if ('' !== $key) {
            $current = array_find($categories, static fn (ShopCategoryView $category): bool => $category->key === $key) ?? throw new NotFoundHttpException();
            $products = array_values(array_filter($products, static fn (ShopProductView $product): bool => $product->category->key === $key));
        }
        $title = $current?->label->in($locale) ?? $this->labels->label($locale->value, 'shop.title');

        return $this->page->render($request, $preview, $this->content->shop($preview), [
            'page' => 'listing',
            'title' => $title,
            'categories' => [
                ['key' => null, 'label' => $this->labels->label($locale->value, 'shop.all'), 'url' => $this->props->listing($locale, $preview), 'current' => null === $current],
                ...array_map(fn (ShopCategoryView $category): array => [...$this->props->category($category, $locale, $preview), 'current' => $category === $current], $categories),
            ],
            'products' => $this->props->cards($products, $locale, $preview),
        ], $title);
    }
}
