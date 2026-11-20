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
    private const string PUBLICATION = '/api/admin/publication';

    public function __construct(private readonly ApiPreload $preload)
    {
    }

    public function __invoke(string $path): Response
    {
        return $this->render('app.html.twig', [
            'preloaded' => $this->preload->json([self::PUBLICATION, ...$this->pageUrls(trim($path, '/'))]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function pageUrls(string $path): array
    {
        return match (true) {
            '' === $path, 'texts' === $path => ['/api/admin/texts'],
            'links' === $path => ['/api/admin/links'],
            'images' === $path => ['/api/admin/artworks'],
            'products' === $path, str_starts_with($path, 'products/') => ['/api/admin/products', '/api/admin/categories'],
            'conventions' === $path => ['/api/admin/conventions'],
            'legal' === $path => ['/api/admin/legal-pages'],
            'settings' === $path => ['/api/admin/shipping-zones'],
            default => [],
        };
    }
}
