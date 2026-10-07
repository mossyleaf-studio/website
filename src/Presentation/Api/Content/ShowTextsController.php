<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ContentQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/texts', name: 'api_texts_show', methods: ['GET'], format: 'json')]
final class ShowTextsController extends AbstractController
{
    public function __invoke(ContentQueries $queries): JsonResponse
    {
        return $this->json($queries->texts());
    }
}
