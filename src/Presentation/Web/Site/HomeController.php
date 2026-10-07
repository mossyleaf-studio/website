<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/', name: 'home', methods: ['GET'])]
final readonly class HomeController
{
    public function __construct(private SitePage $page)
    {
    }

    public function __invoke(): Response
    {
        return $this->page->render('home', true);
    }
}
