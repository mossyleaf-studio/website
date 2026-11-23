<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\PhotoView;
use App\Application\Content\ContentQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/shop/{slug}', name: 'shop_product', requirements: ['_locale' => 'en|fr', 'slug' => '[a-z0-9-]+'], methods: ['GET'], stateless: true)]
final readonly class ShopProductController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $content,
        private CatalogueQueries $catalogue,
        private CatalogueProps $props,
        private ProductSeo $seo,
        private ShopPage $page,
    ) {
    }

    public function __invoke(Request $request, string $slug, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $product = $this->catalogue->shopProduct($slug, $preview) ?? throw new NotFoundHttpException();
        $content = $this->content->shop($preview);
        $title = $product->title->in($locale);

        return $this->page->render($request, $preview, $content, [
            'page' => 'product',
            'title' => $title,
            'description' => $product->description->in($locale),
            'price' => $product->price,
            'priceNote' => $content->priceNote->in($locale),
            'category' => $this->props->category($product->category, $locale, $preview),
            ...(null === $product->collection ? [] : ['collection' => $this->props->collection($product->collection, $locale, $preview)]),
            'photos' => array_map(static fn (PhotoView $photo): array => CatalogueProps::image($photo, $title), $product->photos),
            'variants' => $product->variants,
            'soldOut' => $product->soldOut,
            ...($preview ? ['hidden' => $product->hidden] : []),
            'related' => $this->props->cards($this->catalogue->related($product, $preview), $locale, $preview),
        ], $title, seo: $this->seo->of($product, $locale, $request));
    }
}
