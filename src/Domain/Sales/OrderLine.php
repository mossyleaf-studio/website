<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Catalogue\Product;
use App\Domain\Catalogue\ProductVariant;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'shop_order_line')]
class OrderLine
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(length: ProductVariant::MAX_SKU)]
    private string $sku;

    #[ORM\Column(length: Product::MAX_DISPLAY_NAME)]
    private string $name;

    #[ORM\Column(length: Product::MAX_DISPLAY_NAME)]
    private string $title;

    #[ORM\Column(length: ProductVariant::MAX_NAME, nullable: true)]
    private ?string $variant;

    #[ORM\Column]
    private int $unitPrice;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column]
    private int $position;

    public function __construct(Order $order, string $sku, string $name, string $title, ?string $variant, int $unitPrice, int $quantity, int $position)
    {
        $this->id = new Ulid();
        $this->order = $order;
        $this->sku = $sku;
        $this->name = $name;
        $this->title = $title;
        $this->variant = $variant;
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->position = $position;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function variant(): ?string
    {
        return $this->variant;
    }

    public function unitPrice(): int
    {
        return $this->unitPrice;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function total(): int
    {
        return $this->unitPrice * $this->quantity;
    }
}
