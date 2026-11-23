<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\ContentQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/favicon', name: 'site_favicon', methods: ['GET'], stateless: true)]
final readonly class FaviconController
{
    private const string FALLBACK = '/favicon.svg';

    public function __construct(private ContentQueries $queries)
    {
    }

    public function __invoke(): RedirectResponse
    {
        $response = new RedirectResponse($this->queries->publicLogoUrl() ?? self::FALLBACK);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
