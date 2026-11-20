<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ConventionQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/conventions', name: 'api_conventions_list', methods: ['GET'], format: 'json')]
final class ListConventionsController extends AbstractController
{
    public function __invoke(ConventionQueries $queries): JsonResponse
    {
        return $this->json($queries->all());
    }
}
