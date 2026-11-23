<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\HideCollection\HideCollection;
use App\Application\Catalogue\HideCollection\HideCollectionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/collections/{id}/visibility', name: 'api_collections_hide', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class HideCollectionController extends AbstractController
{
    public function __invoke(Ulid $id, HideCollectionHandler $hideCollection): JsonResponse
    {
        return $this->json($hideCollection(new HideCollection($id)));
    }
}
