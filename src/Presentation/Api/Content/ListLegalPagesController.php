<?php

declare(strict_types=1);

namespace App\Presentation\Api\Content;

use App\Application\Content\ContentQueries;
use App\Application\Content\LegalPageView;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/legal-pages', name: 'api_legal_pages_list', methods: ['GET'], format: 'json')]
final class ListLegalPagesController extends AbstractController
{
    public function __invoke(ContentQueries $queries, ShopUrls $urls): JsonResponse
    {
        return $this->json(array_map(static fn (LegalPageView $page): AdminLegalPage => AdminLegalPage::of($page, $urls), $queries->legalPages()));
    }
}
