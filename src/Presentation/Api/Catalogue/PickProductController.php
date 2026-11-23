<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\PickProduct\PickProduct;
use App\Application\Catalogue\PickProduct\PickProductHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/pick', name: 'api_products_pick', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class PickProductController extends AbstractController
{
    public function __invoke(Ulid $id, PickProductHandler $pickProduct, AdminProducts $products): JsonResponse
    {
        return $this->json($products->one($pickProduct(new PickProduct($id))));
    }
}
