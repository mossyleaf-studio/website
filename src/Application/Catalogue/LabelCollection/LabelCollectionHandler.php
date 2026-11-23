<?php

declare(strict_types=1);

namespace App\Application\Catalogue\LabelCollection;

use App\Application\Catalogue\CollectionView;
use App\Application\Transaction;
use App\Domain\Catalogue\CollectionRepository;
use App\Domain\Shared\Translation;

final readonly class LabelCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(LabelCollection $command): CollectionView
    {
        $collection = $this->collections->get($command->id);
        $collection->relabel(Translation::of($command->label, $command->labelFr));
        $this->transaction->commit();

        return CollectionView::of($collection, $this->collections->productsOnSale());
    }
}
