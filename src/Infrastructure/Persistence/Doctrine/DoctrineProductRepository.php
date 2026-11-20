<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductPhoto;
use App\Domain\Catalogue\ProductRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

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

    public function get(Ulid $id): Product
    {
        return $this->entityManager->find(Product::class, $id) ?? throw new NotFound('product', (string) $id);
    }

    public function withSlug(string $slug): ?Product
    {
        return $this->entityManager->getRepository(Product::class)->findOneBy(['slug' => $slug]);
    }

    public function all(): array
    {
        return $this->entityManager
            ->createQuery('SELECT p, c, v, ph FROM '.Product::class.' p JOIN p.category c LEFT JOIN p.variants v LEFT JOIN p.photos ph ORDER BY p.position ASC, p.id ASC')
            ->getResult();
    }

    public function forSale(bool $hiddenToo): array
    {
        return $this->entityManager
            ->createQuery('SELECT p, c, v, ph FROM '.Product::class.' p JOIN p.category c LEFT JOIN p.variants v LEFT JOIN p.photos ph WHERE p.retiredAt IS NULL AND p.price > 0'.($hiddenToo ? '' : ' AND p.visible = true').' ORDER BY p.position ASC, p.id ASC')
            ->getResult();
    }

    public function photoFiles(): array
    {
        $photos = $this->entityManager->getRepository(ProductPhoto::class)->findAll();

        return [
            ...array_map(static fn (ProductPhoto $photo): string => $photo->file(), $photos),
            ...array_map(static fn (ProductPhoto $photo): string => $photo->thumbFile(), $photos),
        ];
    }
}
