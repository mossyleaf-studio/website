<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ReorderCategories\ReorderCategories;
use App\Application\Catalogue\ReorderCategories\ReorderCategoriesHandler;
use App\Presentation\Api\Content\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/categories/order', name: 'api_categories_order', methods: ['PUT'], format: 'json')]
final class ReorderCategoriesController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, ReorderCategoriesHandler $reorderCategories): JsonResponse
    {
        return $this->json($reorderCategories(new ReorderCategories($payload->ids)));
    }
}
