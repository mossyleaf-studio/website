<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Content\PublishedSite;
use App\Domain\Content\PublishedSiteRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePublishedSiteRepository implements PublishedSiteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): ?PublishedSite
    {
        return $this->entityManager->getRepository(PublishedSite::class)->findOneBy([]);
    }

    public function add(PublishedSite $site): void
    {
        $this->entityManager->persist($site);
    }
}
