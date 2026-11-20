<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ShopReadiness;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/shop/readiness', name: 'api_readiness_show', methods: ['GET'], format: 'json')]
final class ShowShopReadinessController extends AbstractController
{
    public function __invoke(ShopReadiness $readiness): JsonResponse
    {
        return $this->json($readiness->check());
    }
}
