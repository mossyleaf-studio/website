<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\AtomicChange;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAtomicChange implements AtomicChange
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function apply(callable $change): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $change());
    }
}
