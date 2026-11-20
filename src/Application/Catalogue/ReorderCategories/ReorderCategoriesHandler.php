<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderCategories;

use App\Application\Catalogue\CategoryView;
use App\Application\Transaction;
use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Shared\Ordering;

final readonly class ReorderCategoriesHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private Transaction $transaction,
    ) {
    }

    /** @return list<CategoryView> */
    public function __invoke(ReorderCategories $command): array
    {
        $categories = $this->categories->all();
        Ordering::apply($categories, $command->ids);
        $this->transaction->commit();

        usort($categories, static fn (Category $a, Category $b): int => $a->position() <=> $b->position());

        return CategoryView::list($categories);
    }
}
