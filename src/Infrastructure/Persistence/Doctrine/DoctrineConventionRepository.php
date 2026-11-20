<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Content\Convention;
use App\Domain\Content\ConventionRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineConventionRepository implements ConventionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Convention $convention): void
    {
        $this->entityManager->persist($convention);
    }

    public function remove(Convention $convention): void
    {
        $this->entityManager->remove($convention);
    }

    public function get(Ulid $id): Convention
    {
        return $this->entityManager->find(Convention::class, $id) ?? throw new NotFound('convention', (string) $id);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Convention::class)->findBy([], ['startsOn' => 'ASC', 'endsOn' => 'ASC', 'id' => 'ASC']);
    }
}
