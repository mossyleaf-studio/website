<?php

declare(strict_types=1);

namespace App\Presentation\Web\Shop;

use App\Application\Content\ConventionView;
use App\Application\Content\LocalizedText;
use App\Domain\Shared\Locale;

final readonly class LocalizedConvention
{
    private function __construct(
        public string $id,
        public string $name,
        public string $place,
        public string $startsOn,
        public string $endsOn,
        public ?string $url,
        public string $note,
    ) {
    }

    public static function of(ConventionView $convention, Locale $locale): self
    {
        return new self($convention->id, $convention->name, $convention->place, $convention->startsOn, $convention->endsOn, $convention->url, LocalizedText::of($convention->note, $convention->noteFr)->in($locale));
    }

    /**
     * @param list<ConventionView> $conventions
     *
     * @return list<self>
     */
    public static function list(array $conventions, Locale $locale): array
    {
        return array_map(static fn (ConventionView $convention): self => self::of($convention, $locale), $conventions);
    }
}
