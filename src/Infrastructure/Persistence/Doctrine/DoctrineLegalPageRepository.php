<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Content\LegalKind;
use App\Domain\Content\LegalPage;
use App\Domain\Content\LegalPageRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLegalPageRepository implements LegalPageRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(LegalKind $kind): LegalPage
    {
        return $this->entityManager->find(LegalPage::class, $kind->value) ?? LegalPage::blank($kind);
    }

    public function all(): array
    {
        return array_map($this->get(...), LegalKind::cases());
    }

    public function save(LegalPage $page): void
    {
        $this->entityManager->persist($page);
    }
}
