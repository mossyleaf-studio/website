<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use App\Presentation\Api\Shop\OrderStatusJson;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/order/{token}', name: 'shop_order', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ShopOrderController
{
    private const string ROBOTS = 'noindex, nofollow';

    public function __construct(
        private OrderStatusJson $status,
        private ShopUrls $urls,
        private ContentQueries $content,
        private SiteLabels $labels,
        private ShopPage $page,
    ) {
    }

    public function __invoke(Request $request, string $token, bool $preview = false): Response
    {
        $order = $this->status->of($token, $request) ?? throw new NotFoundHttpException();
        $locale = ShopUrls::locale($request);
        $title = $this->labels->label($locale->value, 'order.title');

        $response = $this->page->render($request, $preview, $this->content->shop($preview), [
            'page' => 'order',
            'title' => $title,
            'order' => $order,
            'statusUrl' => $this->urls->to('api_shop_orders_status', $locale, $preview, ['token' => $token]),
        ], $title, seo: ['robots' => self::ROBOTS, 'canonical' => null, 'alternates' => []]);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', self::ROBOTS);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
