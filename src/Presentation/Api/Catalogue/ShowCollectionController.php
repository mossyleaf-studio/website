<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ShowCollection\ShowCollection;
use App\Application\Catalogue\ShowCollection\ShowCollectionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/collections/{id}/visibility', name: 'api_collections_show', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class ShowCollectionController extends AbstractController
{
    public function __invoke(Ulid $id, ShowCollectionHandler $showCollection): JsonResponse
    {
        return $this->json($showCollection(new ShowCollection($id)));
    }
}
