<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Catalogue\Exception\NegativeAmount;
use App\Domain\Shared\Exception\EmptyText;
use App\Domain\Shared\Exception\TextTooLong;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'product_variant')]
class ProductVariant
{
    public const int MAX_SKU = 320;
    public const int MAX_NAME = 255;
    public const int MAX_STOCK = 2_147_483_647;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: self::MAX_SKU, unique: true)]
    private string $sku;

    #[ORM\Column(length: self::MAX_NAME, nullable: true)]
    private ?string $name;

    #[ORM\Column]
    private int $stock;

    #[ORM\Column]
    private int $position;

    public function __construct(Product $product, string $sku, ?string $name, int $stock, int $position)
    {
        if ('' === $sku) {
            throw new EmptyText('sku');
        }
        if (mb_strlen($sku) > self::MAX_SKU) {
            throw new TextTooLong('sku', self::MAX_SKU);
        }

        $this->id = new Ulid();
        $this->product = $product;
        $this->sku = $sku;
        $this->rewrite($name, $stock, $position);
    }

    public function rewrite(?string $name, int $stock, int $position): void
    {
        $name = trim($name ?? '');
        if (mb_strlen($name) > self::MAX_NAME) {
            throw new TextTooLong('variant_name', self::MAX_NAME);
        }
        if ($stock < 0) {
            throw new NegativeAmount('stock');
        }

        $this->name = '' === $name ? null : $name;
        $this->stock = $stock;
        $this->position = $position;
    }

    public function take(int $quantity): int
    {
        $taken = max(0, min($quantity, $this->stock));
        $this->stock -= $taken;

        return $taken;
    }

    public function giveBack(int $quantity): void
    {
        $this->stock += $quantity;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    public function position(): int
    {
        return $this->position;
    }
}
