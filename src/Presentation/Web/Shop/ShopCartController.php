<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/cart', name: 'shop_cart', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ShopCartController
{
    public const string DEFAULT_COUNTRY = 'FR';

    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $content,
        private CartCountries $countries,
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
        $title = $this->labels->label($locale->value, 'cart.title');
        $robots = $preview ? 'noindex, nofollow' : 'noindex';

        $response = $this->page->render($request, $preview, $this->content->shop($preview), [
            'page' => 'cart',
            'title' => $title,
            'quoteUrl' => $this->urls->to('api_shop_quote', $locale, $preview),
            'countries' => $this->countries->deliverable($locale),
            'defaultCountry' => self::DEFAULT_COUNTRY,
            'shopUrl' => $this->urls->to('shop_listing', $locale, $preview),
            'checkoutUrl' => null,
        ], $title, seo: ['robots' => $robots, 'canonical' => null, 'alternates' => []]);
        $response->headers->set('X-Robots-Tag', $robots);

        return $response;
    }
}
