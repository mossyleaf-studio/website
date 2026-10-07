<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteLink;

use App\Application\Transaction;
use App\Domain\Content\LinkRepository;

final readonly class DeleteLinkHandler
{
    public function __construct(
        private LinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteLink $command): void
    {
        $this->links->remove($this->links->get($command->id));
        $this->transaction->commit();
    }
}
