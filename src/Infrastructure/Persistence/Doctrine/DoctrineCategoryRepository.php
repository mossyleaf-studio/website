<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Catalogue\Category;
use App\Domain\Catalogue\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Category $category): void
    {
        $this->entityManager->persist($category);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Category::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
