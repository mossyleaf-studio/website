<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shop;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/api/shop/{_locale}/orders/{token}', name: 'api_shop_orders_status', requirements: ['_locale' => 'en|fr'], methods: ['GET'], format: 'json', stateless: true)]
final readonly class OrderStatusController
{
    public function __construct(private OrderStatusJson $status)
    {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $response = new JsonResponse($this->status->of($token, $request) ?? throw new NotFoundHttpException());
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
