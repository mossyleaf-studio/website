<?php

declare(strict_types=1);

namespace App\Application\Content\EditLink;

use App\Application\Content\LinkView;
use App\Application\Transaction;
use App\Domain\Content\LinkRepository;

final readonly class EditLinkHandler
{
    public function __construct(
        private LinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLink $command): LinkView
    {
        $link = $this->links->get($command->id);
        $link->edit($command->title, $command->description, $command->url, $command->tape);
        $this->transaction->commit();

        return LinkView::of($link);
    }
}
