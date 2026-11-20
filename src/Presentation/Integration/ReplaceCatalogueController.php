<?php

declare(strict_types=1);

namespace App\Presentation\Integration;

use App\Application\Catalogue\ReplaceCatalogue\ReplaceCatalogueHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/integrations/catalogue', name: 'integration_catalogue_replace', methods: ['PUT'], format: 'json', stateless: true)]
final class ReplaceCatalogueController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] CataloguePayload $payload, ReplaceCatalogueHandler $replaceCatalogue): JsonResponse
    {
        return $this->json($replaceCatalogue($payload->command()));
    }
}
