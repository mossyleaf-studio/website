<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\EditConvention\EditConvention;
use App\Application\Content\EditConvention\EditConventionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/conventions/{id}', name: 'api_conventions_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditConventionController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] ConventionPayload $payload, EditConventionHandler $editConvention): JsonResponse
    {
        return $this->json($editConvention(new EditConvention($id, $payload->name, $payload->place, $payload->startsOn(), $payload->endsOn(), $payload->url, $payload->note, $payload->noteFr)));
    }
}
