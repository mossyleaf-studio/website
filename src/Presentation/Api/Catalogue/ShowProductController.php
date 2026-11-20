<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ShowProduct\ShowProduct;
use App\Application\Catalogue\ShowProduct\ShowProductHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/visibility', name: 'api_products_show', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class ShowProductController extends AbstractController
{
    public function __invoke(Ulid $id, ShowProductHandler $showProduct): JsonResponse
    {
        return $this->json($showProduct(new ShowProduct($id)));
    }
}
