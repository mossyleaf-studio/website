<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ContentQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/artworks', name: 'api_artworks_list', methods: ['GET'], format: 'json')]
final class ListArtworksController extends AbstractController
{
    public function __invoke(ContentQueries $queries): JsonResponse
    {
        return $this->json($queries->artworks());
    }
}
