<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ShowCollection;

use App\Application\Catalogue\CollectionView;
use App\Application\Transaction;
use App\Domain\Catalogue\CollectionRepository;

final readonly class ShowCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ShowCollection $command): CollectionView
    {
        $collection = $this->collections->get($command->id);
        $collection->show();
        $this->transaction->commit();

        return CollectionView::of($collection, $this->collections->productsOnSale());
    }
}
