<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ShopContentView;
use App\Application\Content\ShopLinkView;
use App\Domain\Shared\Locale;
use Symfony\Component\HttpFoundation\Request;

final readonly class StudioSeo
{
    public function __construct(private ShopUrls $urls)
    {
    }

    /** @return array{jsonLd: string} */
    public function of(ShopContentView $content, Locale $locale, Request $request): array
    {
        $home = $this->urls->canonical('shop_home', $locale);
        $description = $content->metaDescription->in($locale);
        $image = LocalizedArtwork::featured($content, $locale) ?? $content->logo;

        $studio = [
            '@type' => 'Organization',
            '@id' => $home.'#studio',
            'name' => $content->studioName,
            'url' => $home,
            'description' => $description,
            'sameAs' => array_map(static fn (ShopLinkView $link): string => $link->url, $content->links),
            ...(null === $content->logo ? [] : ['logo' => $request->getUriForPath($content->logo->url)]),
            ...(null === $image ? [] : ['image' => $request->getUriForPath($image->url)]),
        ];

        return ['jsonLd' => json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                $studio,
                ['@type' => 'WebSite', '@id' => $home.'#website', 'url' => $home, 'name' => $content->studioName, 'description' => $description, 'inLanguage' => $locale->value, 'publisher' => ['@id' => $home.'#studio']],
            ],
        ], ShopPage::SAFE_IN_HTML | \JSON_THROW_ON_ERROR)];
    }
}
