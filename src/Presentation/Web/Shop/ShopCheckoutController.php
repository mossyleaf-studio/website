<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use App\Application\Content\ShopLegalPageView;
use App\Application\Sales\PaymentGateway;
use App\Domain\Content\LegalKind;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/checkout', name: 'shop_checkout', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ShopCheckoutController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $content,
        private CartCountries $countries,
        private SiteLabels $labels,
        private ShopPage $page,
        private PaymentGateway $gateway,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }
        if (!$this->gateway->isReady()) {
            throw new NotFoundHttpException();
        }

        $locale = ShopUrls::locale($request);
        $content = $this->content->shop($preview);
        $title = $this->labels->label($locale->value, 'checkout.title');
        $robots = $preview ? 'noindex, nofollow' : 'noindex';
        $terms = array_find($content->legal, static fn (ShopLegalPageView $page): bool => LegalKind::Terms->value === $page->kind && '' !== $page->text->in($locale));

        $response = $this->page->render($request, $preview, $content, [
            'page' => 'checkout',
            'title' => $title,
            'quoteUrl' => $this->urls->to('api_shop_quote', $locale, $preview),
            'ordersUrl' => $this->urls->to('api_shop_orders_place', $locale, $preview),
            'countries' => $this->countries->deliverable($locale),
            'defaultCountry' => ShopCartController::DEFAULT_COUNTRY,
            'termsUrl' => null === $terms ? null : $this->urls->to('shop_legal', $locale, $preview, ['kind' => $terms->kind]),
            'cartUrl' => $this->urls->to('shop_cart', $locale, $preview),
        ], $title, seo: ['robots' => $robots, 'canonical' => null, 'alternates' => []]);
        $response->headers->set('X-Robots-Tag', $robots);

        return $response;
    }
}
