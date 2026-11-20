<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Catalogue\CatalogueQueries;
use App\Application\Content\ContentQueries;
use App\Application\Content\ShopLegalPageView;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
#[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'], format: 'xml')]
final readonly class SitemapController
{
    private const array PAGES = ['shop_home', 'shop_listing'];
    private const array CONTENT_PAGES = ['shop_conventions', 'shop_about', 'shop_contact'];
    private const array HREFLANG = [Locale::Fr, Locale::En];

    public function __construct(
        private ContentQueries $queries,
        private CatalogueQueries $catalogue,
        private UrlGeneratorInterface $urls,
        private ShopUrls $shopUrls,
    ) {
    }

    public function __invoke(): Response
    {
        $response = new Response($this->queries->shopIsOpen() ? $this->shop() : $this->note(), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    private function note(): string
    {
        $publishedAt = $this->queries->public()->publishedAt;
        $lastModified = null === $publishedAt ? '' : \sprintf('<lastmod>%s</lastmod>', $publishedAt->format('Y-m-d'));
        $home = htmlspecialchars($this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL), \ENT_XML1);

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <url><loc>{$home}</loc>{$lastModified}</url>
            </urlset>

            XML;
    }

    private function shop(): string
    {
        $pages = array_map(static fn (string $route): array => [$route, []], self::PAGES);
        foreach ($this->catalogue->shop(false) as $product) {
            $pages[] = ['shop_product', ['slug' => $product->slug]];
        }
        foreach (self::CONTENT_PAGES as $route) {
            $pages[] = [$route, []];
        }
        foreach (array_filter($this->queries->shop(false)->legal, static fn (ShopLegalPageView $page): bool => '' !== $page->text->in(Locale::En)) as $page) {
            $pages[] = ['shop_legal', ['kind' => $page->kind]];
        }

        $urls = '';
        foreach ($pages as [$route, $parameters]) {
            $alternates = '';
            foreach (self::HREFLANG as $language) {
                $alternates .= $this->alternate($language->value, $this->shopUrls->canonical($route, $language, $parameters));
            }
            $alternates .= $this->alternate('x-default', $this->shopUrls->canonical($route, Locale::En, $parameters));
            foreach (Locale::cases() as $locale) {
                $urls .= \sprintf("  <url><loc>%s</loc>%s</url>\n", htmlspecialchars($this->shopUrls->canonical($route, $locale, $parameters), \ENT_XML1), $alternates);
            }
        }

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
            {$urls}</urlset>

            XML;
    }

    private function alternate(string $hreflang, string $href): string
    {
        return \sprintf('<xhtml:link rel="alternate" hreflang="%s" href="%s"/>', $hreflang, htmlspecialchars($href, \ENT_XML1));
    }
}
