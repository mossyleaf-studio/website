<?php

declare(strict_types=1);

namespace App\Application\Content\ChooseFonts;

use App\Application\Content\SiteTextView;
use App\Application\Transaction;
use App\Domain\Content\SiteTextRepository;

final readonly class ChooseFontsHandler
{
    public function __construct(
        private SiteTextRepository $texts,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ChooseFonts $command): SiteTextView
    {
        $text = $this->texts->current();
        $text->chooseFonts($command->heading, $command->body);
        $this->texts->save($text);
        $this->transaction->commit();

        return SiteTextView::of($text);
    }
}
