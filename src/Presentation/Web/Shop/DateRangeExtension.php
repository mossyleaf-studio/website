<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Domain\Shared\Locale;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

final readonly class DateRangeExtension
{
    public function __construct(private LocaleSwitcher $locales)
    {
    }

    #[AsTwigFilter('date_range')]
    public function range(string $startsOn, string $endsOn): string
    {
        return self::format($startsOn, $endsOn, Locale::tryFrom($this->locales->getLocale()) ?? Locale::En);
    }

    public static function format(string $startsOn, string $endsOn, Locale $locale): string
    {
        $start = new \DateTimeImmutable($startsOn, new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable($endsOn, new \DateTimeZone('UTC'));
        $day = static fn (\DateTimeImmutable $date): string => $date->format('j');
        $month = static fn (\DateTimeImmutable $date): string => (string) \IntlDateFormatter::formatObject($date, 'MMMM', $locale->value);

        return match (true) {
            $start->format('Y') !== $end->format('Y') => \sprintf('%s %s %s – %s %s %s', $day($start), $month($start), $start->format('Y'), $day($end), $month($end), $end->format('Y')),
            $start->format('Y-m') !== $end->format('Y-m') => \sprintf('%s %s – %s %s %s', $day($start), $month($start), $day($end), $month($end), $end->format('Y')),
            $start->format('d') !== $end->format('d') => \sprintf('%s–%s %s %s', $day($start), $day($end), $month($end), $end->format('Y')),
            default => \sprintf('%s %s %s', $day($end), $month($end), $end->format('Y')),
        };
    }
}
