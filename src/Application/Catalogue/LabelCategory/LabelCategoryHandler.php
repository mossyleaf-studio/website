<?php

declare(strict_types=1);

namespace App\Application\Catalogue\LabelCategory;

use App\Application\Catalogue\CategoryView;
use App\Application\Transaction;
use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Shared\Translation;

final readonly class LabelCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(LabelCategory $command): CategoryView
    {
        $category = $this->categories->get($command->id);
        $category->relabel(Translation::of($command->label, $command->labelFr));
        $this->transaction->commit();

        return CategoryView::of($category);
    }
}
