<?php

declare(strict_types=1);

namespace App\Application\Content\EditShopNotes;

use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class EditShopNotesHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditShopNotes $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->editShopNotes($command->announcement, $command->announcementFr, $command->priceNote, $command->priceNoteFr);
        $this->texts->save($text);
        $this->transaction->commit();

        return SiteTextView::of($text);
    }
}
