<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Content\Artwork;
use App\Domain\Content\ArtworkRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineArtworkRepository implements ArtworkRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Artwork $artwork): void
    {
        $this->entityManager->persist($artwork);
    }

    public function remove(Artwork $artwork): void
    {
        $this->entityManager->remove($artwork);
    }

    public function get(Ulid $id): Artwork
    {
        return $this->entityManager->find(Artwork::class, $id) ?? throw new NotFound('artwork', (string) $id);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Artwork::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function featured(): ?Artwork
    {
        return $this->entityManager->getRepository(Artwork::class)->findOneBy(['featured' => true]);
    }

    public function nextPosition(): int
    {
        $max = $this->entityManager->createQuery('SELECT MAX(a.position) FROM '.Artwork::class.' a')->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }
}
