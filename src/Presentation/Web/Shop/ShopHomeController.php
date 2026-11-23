<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Catalogue\ShopProductView;
use App\Application\Content\ContentQueries;
use App\Application\Content\ConventionQueries;
use App\Domain\Content\SiteText;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/', name: 'shop_home', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ShopHomeController
{
    private const int SHOWN = 12;

    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $queries,
        private CatalogueQueries $catalogue,
        private CatalogueProps $props,
        private ShopPage $page,
        private ConventionQueries $conventions,
        private StudioSeo $seo,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $content = $this->queries->shop($preview);
        $products = $this->catalogue->shop($preview);
        $nextConvention = $this->conventions->upcoming()[0] ?? null;
        $tiles = $this->props->tiles($products, $locale, $preview);
        $picks = \array_slice(array_values(array_filter($products, static fn (ShopProductView $product): bool => $product->picked)), 0, self::SHOWN);
        $latest = $products;
        usort($latest, static fn (ShopProductView $a, ShopProductView $b): int => strcmp($b->id, $a->id));

        return $this->page->render($request, $preview, $content, [
            'page' => 'home',
            'intro' => $content->intro->in($locale),
            'featured' => LocalizedArtwork::featured($content, $locale),
            'shopUrl' => $tiles[0]['url'] ?? null,
            ...([] === $picks ? [] : ['picks' => $this->props->cards($picks, $locale, $preview)]),
            ...([] === $latest ? [] : ['latest' => $this->props->cards(\array_slice($latest, 0, self::SHOWN), $locale, $preview)]),
            'about' => [
                'title' => $content->aboutTitle->in($locale),
                'text' => SiteText::paragraphs($content->aboutText->in($locale))[0] ?? '',
                'url' => $this->urls->to('shop_about', $locale, $preview),
            ],
            ...([] === $tiles ? [] : ['categories' => $tiles]),
            ...(null === $nextConvention ? [] : ['nextConvention' => LocalizedConvention::of($nextConvention, $locale)]),
        ], seo: $preview ? [] : $this->seo->of($content, $locale, $request));
    }
}
