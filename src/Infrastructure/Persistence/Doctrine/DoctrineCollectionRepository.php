<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Catalogue\Collection;
use App\Domain\Catalogue\CollectionRepository;
use App\Domain\Catalogue\Product;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineCollectionRepository implements CollectionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Collection $collection): void
    {
        $this->entityManager->persist($collection);
    }

    public function remove(Collection $collection): void
    {
        $this->entityManager->remove($collection);
    }

    public function get(Ulid $id): Collection
    {
        return $this->entityManager->find(Collection::class, $id) ?? throw new NotFound('collection', (string) $id);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Collection::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function productsOnSale(): array
    {
        /** @var list<array{id: string, products: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery('SELECT IDENTITY(p.collection) AS id, COUNT(p.id) AS products FROM '.Product::class.' p WHERE p.collection IS NOT NULL AND p.retiredAt IS NULL AND p.price > 0 GROUP BY p.collection')
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => [(string) Ulid::fromString($row['id']), (int) $row['products']], $rows), 1, 0);
    }
}
