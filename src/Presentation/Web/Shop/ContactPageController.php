<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Contact\StudioMailbox;
use App\Application\Content\ContentQueries;
use App\Application\Content\ShopLinkView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/{_locale}/contact', name: 'shop_contact', requirements: ['_locale' => 'en|fr'], methods: ['GET'], stateless: true)]
final readonly class ContactPageController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $queries,
        private ShopPage $page,
        private SiteLabels $labels,
        private StudioMailbox $mailbox,
    ) {
    }

    public function __invoke(Request $request, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $content = $this->queries->shop($preview);
        $title = $this->labels->label($locale->value, 'contact.title');

        return $this->page->render($request, $preview, $content, [
            'page' => 'contact',
            'title' => $title,
            'endpoint' => $this->mailbox->isOpen() ? $this->urls->to('api_shop_contact', $locale, $preview) : null,
            'links' => array_map(
                static fn (ShopLinkView $link): array => ['id' => $link->id, 'title' => $link->title->in($locale), 'description' => $link->description->in($locale), 'url' => $link->url, 'tape' => $link->tape],
                $content->links,
            ),
        ], $title);
    }
}
