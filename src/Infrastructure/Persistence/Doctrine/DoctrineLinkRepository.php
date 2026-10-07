<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Content\Link;
use App\Domain\Content\LinkRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineLinkRepository implements LinkRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Link $link): void
    {
        $this->entityManager->persist($link);
    }

    public function remove(Link $link): void
    {
        $this->entityManager->remove($link);
    }

    public function get(Ulid $id): Link
    {
        return $this->entityManager->find(Link::class, $id) ?? throw new NotFound('link', (string) $id);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Link::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function nextPosition(): int
    {
        $max = $this->entityManager->createQuery('SELECT MAX(l.position) FROM '.Link::class.' l')->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }
}
