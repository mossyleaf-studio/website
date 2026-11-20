<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\PublicSnapshot;
use App\Domain\Content\PublicPage;
use App\Domain\Shared\Locale;
use App\Presentation\Web\Shop\ShopUrls;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class SitePage
{
    public function __construct(
        private Environment $twig,
        private ShopUrls $urls,
    ) {
    }

    public function render(Request $request, PublicSnapshot $snapshot, bool $draft): Response
    {
        $response = new Response();
        if ($draft) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } else {
            $response->setPublic();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('must-revalidate');
            if (null !== $snapshot->publishedAt) {
                $response->setLastModified($snapshot->publishedAt);
                $response->setEtag(hash('xxh128', PublicPage::Note->value.$snapshot->content));
                if ($response->isNotModified($request)) {
                    return $response;
                }
            }
        }

        return $response->setContent($this->twig->render('site.html.twig', [
            'site' => json_decode($snapshot->content, true, flags: \JSON_THROW_ON_ERROR),
            'content' => $snapshot->content,
            'draft' => $draft ? $this->urls->draft(Locale::En) : null,
        ]));
    }
}
