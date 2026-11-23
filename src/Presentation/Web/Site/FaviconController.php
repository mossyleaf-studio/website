<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\ContentQueries;
use App\Application\Content\FaviconPainter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/favicon', name: 'site_favicon', methods: ['GET'], stateless: true)]
final readonly class FaviconController
{
    private const int ONE_HOUR = 3600;

    public function __construct(
        private ContentQueries $queries,
        private FaviconPainter $painter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $file = $this->queries->publicLogoFile();
        $png = null === $file ? null : $this->painter->paint($file);

        $response = null === $png ? new Response('', Response::HTTP_NOT_FOUND) : new Response($png, headers: ['Content-Type' => 'image/png']);
        $response->setPublic();
        $response->setMaxAge(self::ONE_HOUR);
        if (null !== $png) {
            $response->setEtag(hash('xxh128', $png));
            $response->isNotModified($request);
        }

        return $response;
    }
}
