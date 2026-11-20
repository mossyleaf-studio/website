<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Sales\ShippingZone;
use App\Domain\Sales\ShippingZoneRepository;
use App\Domain\Sales\Zone;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineShippingZoneRepository implements ShippingZoneRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(Zone $zone): ShippingZone
    {
        return $this->entityManager->find(ShippingZone::class, $zone->value) ?? throw new NotFound('shipping_zone', $zone->value);
    }

    public function all(): array
    {
        $zones = $this->entityManager->getRepository(ShippingZone::class)->findAll();
        usort($zones, static fn (ShippingZone $a, ShippingZone $b): int => array_search($a->zone(), Zone::cases(), true) <=> array_search($b->zone(), Zone::cases(), true));

        return $zones;
    }
}
