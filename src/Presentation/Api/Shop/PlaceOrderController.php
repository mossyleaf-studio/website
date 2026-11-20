<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use App\Application\Sales\PlaceOrder\PlaceOrder;
use App\Application\Sales\PlaceOrder\PlaceOrderHandler;
use App\Presentation\Api\ProblemResponse;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
#[Route('/api/shop/{_locale}/orders', name: 'api_shop_orders_place', requirements: ['_locale' => 'en|fr'], methods: ['POST'], format: 'json', stateless: true)]
final readonly class PlaceOrderController
{
    public function __construct(
        private PlaceOrderHandler $placeOrder,
        private ShopUrls $urls,
        #[Target('checkout')]
        private RateLimiterFactoryInterface $limiter,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] OrderPayload $payload, bool $preview = false): Response
    {
        $limit = $this->limiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            $response = new ProblemResponse($this->translator->trans('problem.too_many_requests.title'), Response::HTTP_TOO_MANY_REQUESTS, $this->translator->trans('problem.too_many_requests.detail'), 'problem.too_many_requests');
            $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));

            return $response;
        }

        $locale = ShopUrls::locale($request);
        $placed = ($this->placeOrder)(new PlaceOrder($payload->lines(), $payload->country, $payload->delivery(), $locale, $preview, $payload->expectedTotal));

        return new JsonResponse([
            'reference' => $placed->reference,
            'statusUrl' => $this->urls->to('shop_order', $locale, $preview, ['token' => $placed->token]),
            'paymentUrl' => $placed->paymentUrl,
        ], Response::HTTP_CREATED);
    }
}
