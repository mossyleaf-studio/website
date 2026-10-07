<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditIdentity\EditIdentity;
use App\Application\Content\EditIdentity\EditIdentityHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts/identity', name: 'api_texts_identity', methods: ['PUT'], format: 'json')]
final class EditIdentityController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] IdentityPayload $payload, EditIdentityHandler $editIdentity): JsonResponse
    {
        return $this->json($editIdentity(new EditIdentity($payload->studioName, $payload->intro, $payload->metaDescription, $payload->searchTitle)));
    }
}
