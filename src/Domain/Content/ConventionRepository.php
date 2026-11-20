<?php

declare(strict_types=1);

namespace App\Domain\Content;

use Symfony\Component\Uid\Ulid;

interface ConventionRepository
{
    public function add(Convention $convention): void;

    public function remove(Convention $convention): void;

    public function get(Ulid $id): Convention;

    /** @return list<Convention> */
    public function all(): array;
}
