<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/beta/', name: 'beta', methods: ['GET'])]
final readonly class BetaController
{
    public function __construct(private SitePage $page)
    {
    }

    public function __invoke(): Response
    {
        return $this->page->render('beta', false);
    }
}
