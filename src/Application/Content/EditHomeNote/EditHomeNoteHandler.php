<?php

declare(strict_types=1);

namespace App\Application\Content\EditHomeNote;

use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class EditHomeNoteHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditHomeNote $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->editHomeNote($command->title, $command->text);
        $this->texts->save($text);
        $this->transaction->commit();

        return SiteTextView::of($text);
    }
}
