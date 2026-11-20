<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\CreateConvention\CreateConvention;
use App\Application\Content\CreateConvention\CreateConventionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/conventions', name: 'api_conventions_create', methods: ['POST'], format: 'json')]
final class CreateConventionController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] ConventionPayload $payload, CreateConventionHandler $createConvention): JsonResponse
    {
        return $this->json($createConvention(new CreateConvention($payload->name, $payload->place, $payload->startsOn(), $payload->endsOn(), $payload->url, $payload->note, $payload->noteFr)), Response::HTTP_CREATED);
    }
}
