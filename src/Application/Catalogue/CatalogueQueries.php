<?php

declare(strict_types=1);

namespace App\Application\Catalogue;

use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Catalogue\ProductRepository;

final readonly class CatalogueQueries
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
    ) {
    }

    /** @return list<ProductView> */
    public function products(): array
    {
        return ProductView::list($this->products->all());
    }

    /** @return list<CategoryView> */
    public function categories(): array
    {
        return CategoryView::list($this->categories->all());
    }
}
