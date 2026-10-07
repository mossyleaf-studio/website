<?php

declare(strict_types=1);

namespace App\Application\Content;

use Symfony\Component\Uid\Ulid;

interface ArtworkStorage
{
    public function store(Ulid $id, ResizedImage $image): string;

    public function delete(string $file): void;

    public function path(string $file): ?string;

    /** @return list<string> */
    public function files(): array;
}
