<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

interface ProductRepository
{
    public function lock(): void;

    public function add(Product $product): void;

    /** @return list<Product> */
    public function all(): array;
}
