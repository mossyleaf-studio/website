<?php

declare(strict_types=1);

namespace App\Domain\Content;

use Symfony\Component\Uid\Ulid;

interface LinkRepository
{
    public function add(Link $link): void;

    public function remove(Link $link): void;

    public function get(Ulid $id): Link;

    /** @return list<Link> */
    public function all(): array;

    public function nextPosition(): int;
}
