<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use Symfony\Component\Uid\Ulid;

interface CollectionRepository
{
    public function add(Collection $collection): void;

    public function remove(Collection $collection): void;

    public function get(Ulid $id): Collection;

    /** @return list<Collection> */
    public function all(): array;

    /** @return array<string, int> */
    public function productsOnSale(): array;
}
