<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\HideProduct\HideProduct;
use App\Application\Catalogue\HideProduct\HideProductHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/visibility', name: 'api_products_hide', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class HideProductController extends AbstractController
{
    public function __invoke(Ulid $id, HideProductHandler $hideProduct): JsonResponse
    {
        return $this->json($hideProduct(new HideProduct($id)));
    }
}
