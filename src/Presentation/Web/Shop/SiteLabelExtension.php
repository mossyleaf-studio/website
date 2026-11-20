<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

final readonly class SiteLabelExtension
{
    public function __construct(
        private SiteLabels $labels,
        private LocaleSwitcher $locales,
    ) {
    }

    /** @param array<string, scalar> $parameters */
    #[AsTwigFilter('site_t')]
    public function label(string $key, array $parameters = []): string
    {
        return $this->labels->label($this->locales->getLocale(), $key, $parameters);
    }
}
