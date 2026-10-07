<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\ContentQueries;
use App\Domain\Content\PublicPage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/beta/note/', name: 'beta_note', methods: ['GET'])]
final readonly class BetaNoteController
{
    public function __construct(
        private ContentQueries $queries,
        private SitePage $page,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->page->render($this->queries->draft(PublicPage::Note), true);
    }
}
