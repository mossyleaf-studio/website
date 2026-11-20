<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Domain\Catalogue\Category;

final readonly class CategoryView
{
    private function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $labelFr,
    ) {
    }

    public static function of(Category $category): self
    {
        return new self((string) $category->id(), $category->name(), $category->label()->en, $category->label()->fr);
    }

    /**
     * @param list<Category> $categories
     *
     * @return list<self>
     */
    public static function list(array $categories): array
    {
        return array_map(self::of(...), $categories);
    }
}
