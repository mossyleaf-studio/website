<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use App\Application\Content\ConventionQueries;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/conventions', name: 'shop_conventions', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ConventionsPageController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $queries,
        private ConventionQueries $conventions,
        private ShopPage $page,
        private SiteLabels $labels,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $title = $this->labels->label($locale->value, 'conventions.title');

        return $this->page->render($request, $preview, $this->queries->shop($preview), [
            'page' => 'conventions',
            'title' => $title,
            'conventions' => LocalizedConvention::list($this->conventions->upcoming(), $locale),
        ], $title);
    }
}
