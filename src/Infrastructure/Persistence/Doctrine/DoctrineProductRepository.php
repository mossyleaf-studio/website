<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function lock(): void
    {
        $this->entityManager->getConnection()->executeStatement('LOCK TABLE '.$this->entityManager->getClassMetadata(Product::class)->getTableName().' IN SHARE ROW EXCLUSIVE MODE');
    }

    public function add(Product $product): void
    {
        $this->entityManager->persist($product);
    }

    public function all(): array
    {
        return $this->entityManager
            ->createQuery('SELECT p, c, v FROM '.Product::class.' p JOIN p.category c LEFT JOIN p.variants v ORDER BY p.position ASC, p.id ASC')
            ->getResult();
    }
}
