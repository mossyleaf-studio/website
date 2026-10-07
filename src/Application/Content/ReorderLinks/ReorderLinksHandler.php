<?php

declare(strict_types=1);

namespace App\Application\Content\ReorderLinks;

use App\Application\Content\LinkView;
use App\Application\Transaction;
use App\Domain\Content\Link;
use App\Domain\Content\LinkRepository;
use App\Domain\Content\Ordering;

final readonly class ReorderLinksHandler
{
    public function __construct(
        private LinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    /** @return list<LinkView> */
    public function __invoke(ReorderLinks $command): array
    {
        $links = $this->links->all();
        Ordering::apply($links, $command->ids);
        $this->transaction->commit();

        usort($links, static fn (Link $a, Link $b): int => $a->position() <=> $b->position());

        return LinkView::list($links);
    }
}
