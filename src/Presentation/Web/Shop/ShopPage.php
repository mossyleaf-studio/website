<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ShopContentView;
use App\Application\Content\ShopLegalPageView;
use App\Application\Content\ShopLinkView;
use App\Domain\Shared\Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class ShopPage
{
    public const int SAFE_IN_HTML = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
    private const array NAVIGATION = ['home' => 'shop_home', 'shop' => 'shop_listing', 'conventions' => 'shop_conventions', 'about' => 'shop_about', 'contact' => 'shop_contact'];
    private const array CURRENT = ['shop_product' => 'shop_listing'];
    private const array KEPT_QUERY = ['shop_listing' => ['category']];
    private const array OPEN_GRAPH_LOCALES = ['en' => 'en_GB', 'fr' => 'fr_FR'];
    private const string INDEXABLE = 'index';

    public function __construct(
        private Environment $twig,
        private ShopUrls $urls,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $seo
     */
    public function render(Request $request, bool $preview, ShopContentView $content, array $data, ?string $title = null, int $status = Response::HTTP_OK, array $seo = []): Response
    {
        $locale = ShopUrls::locale($request);
        $route = ShopUrls::route($request);
        $parameters = ShopUrls::parameters($request);
        $layout = $this->layout($content, $locale, $preview, $route, $parameters, self::keptQuery($request, $route));
        $seo = [...$this->seo($content, $locale, $preview, $route, $parameters, $title, Response::HTTP_OK === $status), ...$seo];
        $props = json_encode(['layout' => $layout, 'data' => $data], self::SAFE_IN_HTML | \JSON_THROW_ON_ERROR);

        $response = new Response($this->twig->render('shop.html.twig', [
            'props' => $props,
            'layout' => $layout,
            'data' => $data,
            'seo' => $seo,
        ]), $status);
        if ($preview) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        if (\is_string($seo['robots'] ?? null) && str_starts_with($seo['robots'], self::INDEXABLE)) {
            $response->setEtag(hash('xxh128', (string) $response->getContent()));
            $response->isNotModified($request);
        }

        return $response;
    }

    /** @return array<string, string> */
    private static function keptQuery(Request $request, string $route): array
    {
        $kept = [];
        foreach (self::KEPT_QUERY[$route] ?? [] as $name) {
            $value = $request->query->all()[$name] ?? null;
            if (\is_string($value) && '' !== $value) {
                $kept[$name] = $value;
            }
        }

        return $kept;
    }

    /**
     * @param array<string, string> $parameters
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function layout(ShopContentView $content, Locale $locale, bool $preview, string $route, array $parameters, array $query): array
    {
        $current = self::CURRENT[$route] ?? $route;

        return [
            'locale' => $locale->value,
            ...($preview ? ['preview' => true, 'draft' => $this->urls->draft($locale)] : []),
            'studioName' => $content->studioName,
            'intro' => $content->intro->in($locale),
            'logo' => $content->logo,
            'announcement' => $content->announcement->in($locale),
            'navigation' => array_map(
                fn (string $key, string $target): array => ['key' => $key, 'url' => $this->urls->to($target, $locale, $preview), 'current' => $target === $current],
                array_keys(self::NAVIGATION),
                self::NAVIGATION,
            ),
            'languages' => array_map(
                fn (Locale $language): array => ['locale' => $language->value, 'url' => $this->urls->to($route, $language, $preview, [...$parameters, ...$query]), 'current' => $language === $locale],
                Locale::cases(),
            ),
            'cartUrl' => $this->urls->to('shop_cart', $locale, $preview),
            'footer' => [
                'links' => array_map(
                    static fn (ShopLinkView $link): array => ['id' => $link->id, 'title' => $link->title->in($locale), 'url' => $link->url],
                    $content->links,
                ),
                'legal' => array_values(array_map(
                    fn (ShopLegalPageView $page): array => ['kind' => $page->kind, 'url' => $this->urls->to('shop_legal', $locale, $preview, ['kind' => $page->kind])],
                    array_filter($content->legal, static fn (ShopLegalPageView $page): bool => '' !== $page->text->in($locale)),
                )),
            ],
            'fonts' => ['heading' => $content->headingFont, 'body' => $content->bodyFont],
        ];
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array<string, mixed>
     */
    private function seo(ShopContentView $content, Locale $locale, bool $preview, string $route, array $parameters, ?string $title, bool $found): array
    {
        $featured = LocalizedArtwork::featured($content, $locale);
        $image = null === $featured ? $content->logo : $featured;
        $alternates = $found ? [
            ...array_map(fn (Locale $language): array => ['hreflang' => $language->value, 'href' => $this->urls->canonical($route, $language, $parameters)], Locale::cases()),
            ['hreflang' => 'x-default', 'href' => $this->urls->canonical($route, Locale::En, $parameters)],
        ] : [];

        return [
            'title' => null === $title ? $content->pageTitle->in($locale) : \sprintf('%s · %s', $title, $content->studioName),
            'description' => $content->metaDescription->in($locale),
            'robots' => $preview ? 'noindex, nofollow' : ($found ? 'index, follow, max-image-preview:large' : 'noindex'),
            'canonical' => $found ? $this->urls->canonical($route, $locale, $parameters) : null,
            'alternates' => $alternates,
            'locale' => self::OPEN_GRAPH_LOCALES[$locale->value],
            'image' => null === $image ? null : ['url' => $image->url, 'width' => $image->width, 'height' => $image->height, 'alt' => $featured->alt ?? $content->studioName],
            'jsonLd' => null,
        ];
    }
}
