<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ReorderArtworks\ReorderArtworks;
use App\Application\Content\ReorderArtworks\ReorderArtworksHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/artworks/order', name: 'api_artworks_order', methods: ['PUT'], format: 'json')]
final class ReorderArtworksController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, ReorderArtworksHandler $reorderArtworks): JsonResponse
    {
        return $this->json($reorderArtworks(new ReorderArtworks($payload->ids)));
    }
}
