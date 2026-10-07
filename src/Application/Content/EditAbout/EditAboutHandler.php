<?php

declare(strict_types=1);

namespace App\Application\Content\EditAbout;

use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class EditAboutHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditAbout $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->editAbout($command->title, $command->text);
        $this->texts->save($text);
        $this->transaction->commit();

        return SiteTextView::of($text);
    }
}
