<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditGalleryHeading\EditGalleryHeading;
use App\Application\Content\EditGalleryHeading\EditGalleryHeadingHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/gallery', name: 'api_texts_gallery', methods: ['PUT'], format: 'json')]
final class EditGalleryHeadingController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] GalleryPayload $payload, EditGalleryHeadingHandler $editGalleryHeading): JsonResponse
    {
        return $this->json($editGalleryHeading(new EditGalleryHeading($payload->title)));
    }
}
