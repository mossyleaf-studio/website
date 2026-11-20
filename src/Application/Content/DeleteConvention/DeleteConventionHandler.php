<?php

declare(strict_types=1);

namespace App\Application\Content\DeleteConvention;

use App\Application\Transaction;
use App\Domain\Content\ConventionRepository;

final readonly class DeleteConventionHandler
{
    public function __construct(
        private ConventionRepository $conventions,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteConvention $command): void
    {
        $this->conventions->remove($this->conventions->get($command->id));
        $this->transaction->commit();
    }
}
