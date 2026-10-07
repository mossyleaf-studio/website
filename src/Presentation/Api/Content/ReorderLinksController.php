<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ReorderLinks\ReorderLinks;
use App\Application\Content\ReorderLinks\ReorderLinksHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/links/order', name: 'api_links_order', methods: ['PUT'], format: 'json')]
final class ReorderLinksController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, ReorderLinksHandler $reorderLinks): JsonResponse
    {
        return $this->json($reorderLinks(new ReorderLinks($payload->ids)));
    }
}
