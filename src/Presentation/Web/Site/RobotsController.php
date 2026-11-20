<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\ContentQueries;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/robots.txt', name: 'robots', methods: ['GET'], format: 'txt')]
final readonly class RobotsController
{
    private const array PRIVATE_PATHS = ['/admin', '/beta', '/api/', '/login', '/logout'];

    public function __construct(
        private UrlGeneratorInterface $urls,
        private ContentQueries $queries,
        private ShopUrls $shopUrls,
    ) {
    }

    public function __invoke(): Response
    {
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (self::PRIVATE_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        if ($this->queries->shopIsOpen()) {
            foreach (Locale::cases() as $locale) {
                $lines[] = 'Disallow: '.\dirname($this->shopUrls->to('shop_order', $locale, false, ['token' => 'token'])).'/';
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.$this->urls->generate('sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = new Response(implode("\n", $lines)."\n", headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(86400);

        return $response;
    }
}
