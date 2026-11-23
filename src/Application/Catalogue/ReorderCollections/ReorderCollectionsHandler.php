<?php

declare(strict_types=1);

namespace App\Application\Catalogue\ReorderCollections;

use App\Application\Catalogue\CollectionView;
use App\Application\Transaction;
use App\Domain\Catalogue\Collection;
use App\Domain\Catalogue\CollectionRepository;
use App\Domain\Shared\Ordering;

final readonly class ReorderCollectionsHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private Transaction $transaction,
    ) {
    }

    /** @return list<CollectionView> */
    public function __invoke(ReorderCollections $command): array
    {
        $collections = $this->collections->all();
        Ordering::apply($collections, $command->ids);
        $this->transaction->commit();

        usort($collections, static fn (Collection $a, Collection $b): int => $a->position() <=> $b->position());

        return CollectionView::list($collections, $this->collections->productsOnSale());
    }
}
