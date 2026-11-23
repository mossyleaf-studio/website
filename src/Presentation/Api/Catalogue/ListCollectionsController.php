<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\CatalogueQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/collections', name: 'api_collections_list', methods: ['GET'], format: 'json')]
final class ListCollectionsController extends AbstractController
{
    public function __invoke(CatalogueQueries $queries): JsonResponse
    {
        return $this->json($queries->collections());
    }
}
