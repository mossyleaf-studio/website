<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\LabelCategory\LabelCategory;
use App\Application\Catalogue\LabelCategory\LabelCategoryHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/categories/{id}', name: 'api_categories_label', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class LabelCategoryController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] CategoryLabelPayload $payload, LabelCategoryHandler $labelCategory): JsonResponse
    {
        return $this->json($labelCategory(new LabelCategory($id, $payload->label, $payload->labelFr)));
    }
}
