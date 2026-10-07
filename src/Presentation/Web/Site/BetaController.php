<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\ContentQueries;
use App\Domain\Content\PublicPage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/beta/', name: 'beta', methods: ['GET'])]
final readonly class BetaController
{
    public function __construct(
        private ContentQueries $queries,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return $this->page->render($request, $this->queries->draft(PublicPage::Full), true);
    }
}
