<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ContentQueries;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/publication', name: 'api_publication_show', methods: ['GET'], format: 'json')]
final class ShowPublicationController extends AbstractController
{
    public function __invoke(ContentQueries $queries, ShopUrls $urls): JsonResponse
    {
        return $this->json(AdminPublication::of($queries->publication(), $urls));
    }
}
