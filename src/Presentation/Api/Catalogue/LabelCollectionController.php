<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\LabelCollection\LabelCollection;
use App\Application\Catalogue\LabelCollection\LabelCollectionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/collections/{id}', name: 'api_collections_label', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class LabelCollectionController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] CollectionLabelPayload $payload, LabelCollectionHandler $labelCollection): JsonResponse
    {
        return $this->json($labelCollection(new LabelCollection($id, $payload->label, $payload->labelFr)));
    }
}
