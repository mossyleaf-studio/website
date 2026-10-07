<?php

declare(strict_types=1);

namespace App\Application\Content\RemoveLogo;

use App\Application\Content\MediaSweeper;
use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class RemoveLogoHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private MediaSweeper $media,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveLogo $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->removeLogo();
        $this->texts->save($text);
        $this->transaction->commit();
        $this->media->sweep();

        return SiteTextView::of($text);
    }
}
