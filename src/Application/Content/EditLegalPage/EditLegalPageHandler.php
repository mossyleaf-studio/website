<?php

declare(strict_types=1);

namespace App\Application\Content\EditLegalPage;

use App\Application\Content\LegalPageView;
use App\Application\Transaction;
use App\Domain\Content\LegalPageRepository;
use App\Domain\Shared\Translation;
use Psr\Clock\ClockInterface;

final readonly class EditLegalPageHandler
{
    public function __construct(
        private LegalPageRepository $pages,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLegalPage $command): LegalPageView
    {
        $page = $this->pages->get($command->kind);
        $page->write(Translation::of($command->text, $command->textFr), $this->clock->now());
        $this->pages->save($page);
        $this->transaction->commit();

        return LegalPageView::of($page);
    }
}
