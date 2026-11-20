<?php

declare(strict_types=1);

namespace App\Application\Content\CreateConvention;

use App\Application\Content\ConventionQueries;
use App\Application\Content\ConventionView;
use App\Application\Transaction;
use App\Domain\Content\Convention;
use App\Domain\Content\ConventionRepository;
use App\Domain\Shared\Translation;

final readonly class CreateConventionHandler
{
    public function __construct(
        private ConventionRepository $conventions,
        private ConventionQueries $queries,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(CreateConvention $command): ConventionView
    {
        $convention = Convention::plan($command->name, $command->place, $command->startsOn, $command->endsOn, $command->url, Translation::of($command->note, $command->noteFr));
        $this->conventions->add($convention);
        $this->transaction->commit();

        return $this->queries->view($convention);
    }
}
