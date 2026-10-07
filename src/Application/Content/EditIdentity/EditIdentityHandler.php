<?php

declare(strict_types=1);

namespace App\Application\Content\EditIdentity;

use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class EditIdentityHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditIdentity $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->editIdentity($command->studioName, $command->intro, $command->metaDescription);
        $this->texts->save($text);
        $this->transaction->commit();

        return SiteTextView::of($text);
    }
}
