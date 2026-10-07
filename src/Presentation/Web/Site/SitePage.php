<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\PublicSnapshot;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class SitePage
{
    public function __construct(private Environment $twig)
    {
    }

    public function render(PublicSnapshot $snapshot, bool $draft): Response
    {
        $response = new Response($this->twig->render('site.html.twig', [
            'page' => $snapshot->page->value,
            'site' => json_decode($snapshot->content, true, flags: \JSON_THROW_ON_ERROR),
            'content' => $snapshot->content,
            'draft' => $draft,
        ]));

        if ($draft) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } else {
            $response->setPublic();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        return $response;
    }
}
