<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\CreateLink\CreateLink;
use App\Application\Content\CreateLink\CreateLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/links', name: 'api_links_create', methods: ['POST'], format: 'json')]
final class CreateLinkController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] LinkPayload $payload, CreateLinkHandler $createLink): JsonResponse
    {
        return $this->json($createLink(new CreateLink($payload->title, $payload->description, $payload->url, $payload->tape)), Response::HTTP_CREATED);
    }
}
