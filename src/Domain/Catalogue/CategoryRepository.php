<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

interface CategoryRepository
{
    public function add(Category $category): void;

    /** @return list<Category> */
    public function all(): array;
}
