<?php

declare(strict_types=1);

namespace App\Application\Content\EditConvention;

use Symfony\Component\Uid\Ulid;

final readonly class EditConvention
{
    public function __construct(
        public Ulid $id,
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
