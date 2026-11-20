<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use Symfony\Component\Uid\Ulid;

interface CategoryRepository
{
    public function add(Category $category): void;

    public function get(Ulid $id): Category;

    /** @return list<Category> */
    public function all(): array;
}
