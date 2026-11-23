<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Catalogue\CatalogueQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/shop', name: 'shop_listing', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ShopListingController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private CatalogueQueries $catalogue,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $first = $this->catalogue->shopCategories($preview)[0] ?? null;

        return new RedirectResponse(null === $first ? $this->urls->to('shop_home', $locale, $preview) : $this->urls->to('shop_category', $locale, $preview, ['category' => $first->key]));
    }
}
