<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ShopCollectionView;
use App\Application\Catalogue\ShopProductView;
use App\Application\Content\ContentQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/collection/{collection}', name: 'shop_collection', requirements: ['_locale' => 'en|fr', 'collection' => '[a-z0-9-]+'], methods: ['GET'], stateless: true)]
final readonly class ShopCollectionController
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

    public function __invoke(Request $request, string $collection, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $products = array_values(array_filter($this->catalogue->shop($preview), static fn (ShopProductView $product): bool => $product->collection?->key === $collection));
        $shown = ShopCollectionView::shownIn($products)[0] ?? throw new NotFoundHttpException();
        $title = $shown->label->in($locale);
        [$products, $filters] = $this->filters->apply($request, CatalogueFilters::CATEGORY, $products, $preview);

        return $this->page->render($request, $preview, $this->content->shop($preview), [
            'page' => 'collection',
            'title' => $title,
            'collection' => ['key' => $shown->key, 'label' => $title, ...CatalogueProps::hidden($shown, $preview)],
            ...$filters,
            'products' => $this->props->cards($products, $locale, $preview),
        ], $title);
    }
}
