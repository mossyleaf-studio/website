<?php

declare(strict_types=1);

namespace App\Presentation\Api\Catalogue;

use App\Application\Catalogue\ReorderProductPhotos\ReorderProductPhotos;
use App\Application\Catalogue\ReorderProductPhotos\ReorderProductPhotosHandler;
use App\Presentation\Api\Content\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/products/{id}/photos/order', name: 'api_product_photos_order', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class ReorderProductPhotosController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] OrderPayload $payload, ReorderProductPhotosHandler $reorderProductPhotos): JsonResponse
    {
        return $this->json($reorderProductPhotos(new ReorderProductPhotos($id, $payload->ids)));
    }
}
