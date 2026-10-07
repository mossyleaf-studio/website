<?php

declare(strict_types=1);

namespace App\Application\Content\EditGalleryHeading;

use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class EditGalleryHeadingHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditGalleryHeading $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->editGallery($command->title);
        $this->transaction->commit();

        return SiteTextView::of($text);
    }
}
