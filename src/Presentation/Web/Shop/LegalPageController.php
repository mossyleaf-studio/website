<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ContentQueries;
use App\Application\Content\ShopLegalPageView;
use App\Domain\Content\LegalKind;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[AsController]
#[Route('/{_locale}/legal/{kind}', name: 'shop_legal', requirements: ['_locale' => 'en|fr', 'kind' => new EnumRequirement(LegalKind::class)], methods: ['GET'], stateless: true)]
final readonly class LegalPageController
{
    public function __construct(
        private ShopDoor $door,
        private ShopUrls $urls,
        private ContentQueries $queries,
        private ShopPage $page,
        private SiteLabels $labels,
    ) {
    }

    public function __invoke(Request $request, LegalKind $kind, bool $preview = false): Response
    {
        if (!$this->door->admits($preview)) {
            return new RedirectResponse($this->urls->closed());
        }

        $locale = ShopUrls::locale($request);
        $content = $this->queries->shop($preview);
        $page = array_find($content->legal, static fn (ShopLegalPageView $candidate): bool => $candidate->kind === $kind->value);
        $text = $page?->text->in($locale) ?? '';
        if ('' === $text) {
            throw new NotFoundHttpException();
        }
        $title = $this->labels->label($locale->value, 'legal.title.'.$kind->value);

        return $this->page->render($request, $preview, $content, [
            'page' => 'legal',
            'kind' => $kind->value,
            'title' => $title,
            'text' => $text,
            'updatedAt' => $page?->updatedAt,
        ], $title);
    }
}
