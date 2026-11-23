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
#[Route('/{_locale}/category/{category}', name: 'shop_category', requirements: ['_locale' => 'en|fr', 'category' => '[a-z0-9-]+'], methods: ['GET'], stateless: true)]
final readonly class ShopCategoryController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $content,
        private CatalogueQueries $catalogue,
        private CatalogueProps $props,
        private CatalogueFilters $filters,
        private ShopPage $page,
    ) {
    }

    public function __invoke(Request $request, string $category, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $products = array_values(array_filter($this->catalogue->shop($preview), static fn (ShopProductView $product): bool => $product->category->key === $category));
        $shown = ShopCategoryView::shownIn($products)[0] ?? throw new NotFoundHttpException();
        $title = $shown->label->in($locale);
        [$products, $filters] = $this->filters->apply($request, CatalogueFilters::COLLECTION, $products, $preview);

        return $this->page->render($request, $preview, $this->content->shop($preview), [
            'page' => 'category',
            'title' => $title,
            'category' => ['key' => $shown->key, 'label' => $title],
            ...$filters,
            'products' => $this->props->cards($products, $locale, $preview),
        ], $title);
    }
}
