<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
use App\Domain\Catalogue\Product;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Category $category): void
    {
        $this->entityManager->persist($category);
    }

    public function get(Ulid $id): Category
    {
        return $this->entityManager->find(Category::class, $id) ?? throw new NotFound('category', (string) $id);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Category::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function forSale(bool $hiddenToo): array
    {
        return $this->entityManager
            ->createQuery('SELECT c FROM '.Category::class.' c WHERE EXISTS (SELECT p.id FROM '.Product::class.' p WHERE p.category = c AND p.retiredAt IS NULL AND p.price > 0'.($hiddenToo ? '' : ' AND p.visible = true').')')
            ->getResult();
    }
}
