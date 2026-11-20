<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Sales\Exception\NegativeShippingAmount;
use App\Domain\Sales\Exception\ShippingAmountTooLarge;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shipping_zone')]
class ShippingZone
{
    public const int MAX_AMOUNT = 1_000_000;

    #[ORM\Id]
    #[ORM\Column(length: 16)]
    private string $code;

    #[ORM\Column]
    private int $price = 0;

    #[ORM\Column(nullable: true)]
    private ?int $freeFrom = null;

    #[ORM\Column]
    private bool $enabled = false;

    private function __construct(Zone $zone)
    {
        $this->code = $zone->value;
    }

    public static function off(Zone $zone): self
    {
        return new self($zone);
    }

    public function set(int $price, ?int $freeFrom, bool $enabled): void
    {
        if ($price < 0) {
            throw new NegativeShippingAmount('price');
        }
        if (null !== $freeFrom && $freeFrom < 0) {
            throw new NegativeShippingAmount('free_from');
        }
        if ($price > self::MAX_AMOUNT) {
            throw new ShippingAmountTooLarge('price');
        }
        if (null !== $freeFrom && $freeFrom > self::MAX_AMOUNT) {
            throw new ShippingAmountTooLarge('free_from');
        }

        $this->price = $price;
        $this->freeFrom = $freeFrom;
        $this->enabled = $enabled;
    }

    public function isFreeFor(int $goods): bool
    {
        return null !== $this->freeFrom && $goods >= $this->freeFrom;
    }

    public function zone(): Zone
    {
        return Zone::from($this->code);
    }

    public function price(): int
    {
        return $this->price;
    }

    public function freeFrom(): ?int
    {
        return $this->freeFrom;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
