<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReplaceCatalogue;

use Symfony\Component\Uid\Ulid;

final readonly class PushedCollection
{
    public string $name;

    public function __construct(
        public Ulid $id,
        string $name,
    ) {
        $this->name = trim($name);
    }
}
