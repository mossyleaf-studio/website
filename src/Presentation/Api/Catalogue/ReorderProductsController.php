<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ReorderProducts\ReorderProducts;
use App\Application\Catalogue\ReorderProducts\ReorderProductsHandler;
use App\Presentation\Api\Content\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/products/order', name: 'api_products_order', methods: ['PUT'], format: 'json')]
final class ReorderProductsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, ReorderProductsHandler $reorderProducts, AdminProducts $products): JsonResponse
    {
        return $this->json($products->all($reorderProducts(new ReorderProducts($payload->ids))));
    }
}
