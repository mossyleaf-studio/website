<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Content\SiteText;
use App\Domain\Content\SiteTextRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSiteTextRepository implements SiteTextRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): SiteText
    {
        return $this->entityManager->getRepository(SiteText::class)->findOneBy([]) ?? SiteText::initial();
    }

    public function save(SiteText $text): void
    {
        $this->entityManager->persist($text);
    }
}
