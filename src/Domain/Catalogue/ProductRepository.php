<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use Symfony\Component\Uid\Ulid;

interface ProductRepository
{
    public function lock(): void;

    public function add(Product $product): void;

    public function get(Ulid $id): Product;

    public function withSlug(string $slug): ?Product;

    /** @return list<Product> */
    public function all(): array;

    /** @return list<Product> */
    public function forSale(bool $hiddenToo): array;

    /** @return list<string> */
    public function photoFiles(): array;
}
