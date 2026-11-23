<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\UnpickProduct\UnpickProduct;
use App\Application\Catalogue\UnpickProduct\UnpickProductHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/pick', name: 'api_products_unpick', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class UnpickProductController extends AbstractController
{
    public function __invoke(Ulid $id, UnpickProductHandler $unpickProduct, AdminProducts $products): JsonResponse
    {
        return $this->json($products->one($unpickProduct(new UnpickProduct($id))));
    }
}
