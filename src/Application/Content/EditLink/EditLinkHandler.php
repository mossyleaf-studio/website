<?php

declare(strict_types=1);

namespace App\Application\Content\EditLink;

use App\Application\Content\DraftLinkView;
use App\Application\Transaction;
use App\Domain\Content\LinkRepository;

final readonly class EditLinkHandler
{
    public function __construct(
        private LinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLink $command): DraftLinkView
    {
        $link = $this->links->get($command->id);
        $link->edit($command->title, $command->description, $command->url, $command->tape, $command->titleFr, $command->descriptionFr);
        $this->transaction->commit();

        return DraftLinkView::of($link);
    }
}
