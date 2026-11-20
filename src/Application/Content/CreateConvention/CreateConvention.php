<?php

declare(strict_types=1);

namespace App\Application\Content\CreateConvention;

final readonly class CreateConvention
{
    public function __construct(
        public string $name,
        public string $place,
        public \DateTimeImmutable $startsOn,
        public \DateTimeImmutable $endsOn,
        public ?string $url,
        public string $note,
        public string $noteFr,
    ) {
    }
}
