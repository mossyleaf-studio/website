<?php

declare(strict_types=1);

namespace App\Application\Content\CreateLink;

use App\Application\Content\LinkView;
use App\Application\Transaction;
use App\Domain\Content\Link;
use App\Domain\Content\LinkRepository;

final readonly class CreateLinkHandler
{
    public function __construct(
        private LinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(CreateLink $command): LinkView
    {
        $link = Link::create($command->title, $command->description, $command->url, $command->tape, $this->links->nextPosition());
        $this->links->add($link);
        $this->transaction->commit();

        return LinkView::of($link);
    }
}
