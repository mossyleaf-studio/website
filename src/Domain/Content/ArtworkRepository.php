<?php

declare(strict_types=1);

namespace App\Domain\Content;

use Symfony\Component\Uid\Ulid;

interface ArtworkRepository
{
    public function add(Artwork $artwork): void;

    public function remove(Artwork $artwork): void;

    public function get(Ulid $id): Artwork;

    /** @return list<Artwork> */
    public function all(): array;

    public function featured(): ?Artwork;

    public function nextPosition(): int;
}
