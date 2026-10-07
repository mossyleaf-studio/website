<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditLink\EditLink;
use App\Application\Content\EditLink\EditLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/links/{id}', name: 'api_links_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditLinkController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] LinkPayload $payload, EditLinkHandler $editLink): JsonResponse
    {
        return $this->json($editLink(new EditLink($id, $payload->title, $payload->description, $payload->url, $payload->tape)));
    }
}
