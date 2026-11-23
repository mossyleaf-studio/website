<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ReorderCollections\ReorderCollections;
use App\Application\Catalogue\ReorderCollections\ReorderCollectionsHandler;
use App\Presentation\Api\Content\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/collections/order', name: 'api_collections_order', methods: ['PUT'], format: 'json')]
final class ReorderCollectionsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, ReorderCollectionsHandler $reorderCollections): JsonResponse
    {
        return $this->json($reorderCollections(new ReorderCollections($payload->ids)));
    }
}
