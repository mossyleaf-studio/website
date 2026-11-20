<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

final readonly class MoneyExtension
{
    public function __construct(private LocaleSwitcher $locales)
    {
    }

    #[AsTwigFilter('money')]
    public function money(int $cents): string
    {
        return (string) new \NumberFormatter($this->locales->getLocale(), \NumberFormatter::CURRENCY)->formatCurrency($cents / 100, 'EUR');
    }
}
