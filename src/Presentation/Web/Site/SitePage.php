<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Content\ContentQueries;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

final readonly class SitePage
{
    private const int JSON_IN_HTML = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

    public function __construct(
        private ContentQueries $queries,
        private SerializerInterface $serializer,
        private Environment $twig,
    ) {
    }

    public function render(string $page, bool $indexable): Response
    {
        $site = $this->queries->site();

        $response = new Response($this->twig->render('site.html.twig', [
            'page' => $page,
            'site' => $site,
            'indexable' => $indexable,
            'content' => $this->serializer->serialize($site, 'json', [JsonEncode::OPTIONS => self::JSON_IN_HTML]),
        ]));
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
