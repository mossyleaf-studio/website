<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use App\Domain\Content\SiteText;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/about', name: 'shop_about', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class AboutPageController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $queries,
        private ShopPage $page,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $content = $this->queries->shop($preview);
        $title = $content->aboutTitle->in($locale);

        return $this->page->render($request, $preview, $content, [
            'page' => 'about',
            'title' => $title,
            'paragraphs' => SiteText::paragraphs($content->aboutText->in($locale)),
            'featured' => LocalizedArtwork::featured($content, $locale),
            'gallery' => [
                'title' => $content->galleryTitle->in($locale),
                'artworks' => LocalizedArtwork::gallery($content, $locale),
            ],
        ], $title);
    }
}
