<?php

declare(strict_types=1);

namespace App\Application\Content\EditConvention;

use App\Application\Content\ConventionQueries;
use App\Application\Content\ConventionView;
use App\Application\Transaction;
use App\Domain\Content\ConventionRepository;
use App\Domain\Shared\Translation;

final readonly class EditConventionHandler
{
    public function __construct(
        private ConventionRepository $conventions,
        private ConventionQueries $queries,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditConvention $command): ConventionView
    {
        $convention = $this->conventions->get($command->id);
        $convention->revise($command->name, $command->place, $command->startsOn, $command->endsOn, $command->url, Translation::of($command->note, $command->noteFr));
        $this->transaction->commit();

        return $this->queries->view($convention);
    }
}
