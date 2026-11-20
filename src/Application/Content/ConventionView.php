<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\Convention;

final readonly class ConventionView
{
    private const string DAY = 'Y-m-d';

    private function __construct(
        public string $id,
        public string $name,
        public string $place,
        public string $startsOn,
        public string $endsOn,
        public ?string $url,
        public string $note,
        public string $noteFr,
        public bool $upcoming,
    ) {
    }

    public static function of(Convention $convention, \DateTimeImmutable $today): self
    {
        return new self(
            (string) $convention->id(),
            $convention->name(),
            $convention->place(),
            $convention->startsOn()->format(self::DAY),
            $convention->endsOn()->format(self::DAY),
            $convention->url(),
            $convention->note()->en,
            $convention->note()->fr,
            !$convention->isOverOn($today),
        );
    }
}
