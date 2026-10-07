<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use App\Presentation\ApiPreload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/{path}', name: 'app', requirements: ['path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
final class AppShellController extends AbstractController
{
    public function __construct(private readonly ApiPreload $preload)
    {
    }

    public function __invoke(string $path): Response
    {
        return $this->render('app.html.twig', [
            'preloaded' => $this->preload->json($this->pageUrls(trim($path, '/'))),
        ]);
    }

    /**
     * @return list<string>
     */
    private function pageUrls(string $path): array
    {
        return match ($path) {
            '', 'texts' => ['/api/admin/texts'],
            'links' => ['/api/admin/links'],
            'images' => ['/api/admin/artworks'],
            default => [],
        };
    }
}
